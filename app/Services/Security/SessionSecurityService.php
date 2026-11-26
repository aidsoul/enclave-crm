<?php

namespace App\Services\Security;

use App\Enums\ActionType;
use App\Models\ActionLog;
use App\Models\User;
use App\Services\TelegramService;
use App\Services\UserSessionService;
use Illuminate\Http\Request;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class SessionSecurityService
{
    public const DEFAULT_TEMP_BLOCK_TTL = 900; // 15 minutes

    public const DEFAULT_BOT_BLOCK_TTL = 3600; // 1 hour

    public const DEFAULT_CONFIRM_CODE_TTL = 600; // 10 minutes

    public const DEFAULT_MAX_ATTEMPTS = 3;

    public const DEFAULT_ALERT_THROTTLE_TTL = 300; // 5 minutes

    /**
     * Generate a 32-character hex hash of the session token.
     * Fits cleanly within Telegram Bot API callback_data limit (64 bytes).
     */
    public static function hashToken(string $token): string
    {
        return substr(hash('sha256', $token), 0, 32);
    }

    public function __construct(
        protected GeoIpService $geoIpService,
        protected UserSessionService $sessionService,
        protected TelegramService $telegramService
    ) {}

    /**
     * Check if the specified IP address is temporarily blocked.
     */
    public function isIpBlocked(string $ip): bool
    {
        try {
            return (bool) Redis::exists("security:ip_blocked:{$ip}");
        } catch (\Throwable $e) {
            Log::error('Redis isIpBlocked error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Get remaining block time in seconds.
     */
    public function getBlockRemainingSeconds(string $ip): int
    {
        try {
            $ttl = (int) Redis::ttl("security:ip_blocked:{$ip}");

            return max(0, $ttl);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Place an IP into temporary block.
     */
    public function blockIp(string $ip, ?int $ttl = null, string $reason = 'manual'): void
    {
        $ttl = $ttl ?? (int) config('security.temp_block_duration', self::DEFAULT_TEMP_BLOCK_TTL);

        try {
            $payload = json_encode([
                'reason' => $reason,
                'blocked_at' => now()->timestamp,
                'expires_at' => now()->timestamp + $ttl,
            ]);

            Redis::setex("security:ip_blocked:{$ip}", $ttl, (string) $payload);

            Log::warning("IP temporarily blocked: {$ip}", [
                'reason' => $reason,
                'ttl' => $ttl,
            ]);
        } catch (\Throwable $e) {
            Log::error('Redis blockIp error: '.$e->getMessage());
        }
    }

    /**
     * Remove temporary block from an IP address.
     */
    public function unblockIp(string $ip): bool
    {
        try {
            return (bool) Redis::del("security:ip_blocked:{$ip}");
        } catch (\Throwable $e) {
            Log::error('Redis unblockIp error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Check if the session is accessed from an authorized/verified IP address.
     * Reuses already decoded session data if passed to eliminate redundant Redis roundtrips.
     *
     * @param  array<string, mixed>|null  $cachedSessionData
     * @return array{verified: bool, status: string, error?: string, ip?: string, country?: string, session_hash?: string, remaining?: int}
     */
    public function checkSessionIp(User $user, string $token, string $currentIp, Request $request, ?array $cachedSessionData = null): array
    {
        if ($this->isIpBlocked($currentIp)) {
            return [
                'verified' => false,
                'status' => 'blocked',
                'remaining' => $this->getBlockRemainingSeconds($currentIp),
                'error' => 'Доступ с вашего IP-адреса временно заблокирован.',
            ];
        }

        if (! (bool) config('security.ip_verification_enabled', true)) {
            return ['verified' => true, 'status' => 'disabled'];
        }

        $sessionData = $cachedSessionData;
        if ($sessionData === null) {
            $sessionJson = Redis::get("session:{$token}") ?: Redis::get("session_data:{$token}");
            if (! $sessionJson) {
                return ['verified' => false, 'status' => 'not_found', 'error' => 'Сессия не найдена'];
            }
            $sessionData = json_decode((string) $sessionJson, true) ?: [];
        }

        // Legacy session support: initialize verified_ips if empty
        if (empty($sessionData['verified_ips']) && empty($sessionData['ip'])) {
            $country = $this->geoIpService->resolveCountryName($currentIp, $request);
            $sessionData['ip'] = $currentIp;
            $sessionData['country'] = $country;
            $sessionData['verified_ips'] = [$currentIp];

            $ttl = Redis::ttl("session:{$token}");
            if ($ttl <= 0) {
                $ttl = UserSessionService::SESSION_TTL;
            }

            $encoded = json_encode($sessionData);
            Redis::setex("session:{$token}", $ttl, (string) $encoded);
            Redis::setex("session_data:{$token}", $ttl, (string) $encoded);

            return ['verified' => true, 'status' => 'initialized'];
        }

        $verifiedIps = $sessionData['verified_ips'] ?? [];
        if (! is_array($verifiedIps)) {
            $verifiedIps = [];
        }

        if (! empty($sessionData['ip']) && ! in_array($sessionData['ip'], $verifiedIps, true)) {
            $verifiedIps[] = $sessionData['ip'];
        }

        if (in_array($currentIp, $verifiedIps, true)) {
            return ['verified' => true, 'status' => 'ok'];
        }

        // IP is unverified -> trigger verification flow & alert
        $verification = $this->initiateVerification($user, $token, $currentIp, $request);

        $hasCloudPassword = $user->hasCloudPassword();

        return [
            'verified' => false,
            'status' => 'verification_required',
            'ip' => $currentIp,
            'country' => $verification['country'],
            'session_hash' => $verification['session_hash'],
            'has_cloud_password' => $hasCloudPassword,
            'error' => $hasCloudPassword
                ? 'Обнаружен вход с нового IP-адреса. Требуется ввод облачного пароля.'
                : 'Обнаружен вход с нового IP-адреса. Требуется установка облачного пароля.',
        ];
    }

    /**
     * Initiate verification by generating a key and sending an alert to Telegram bot.
     *
     * @return array{code: string, session_hash: string, country: string}
     */
    public function initiateVerification(User $user, string $token, string $ip, Request $request): array
    {
        $sessionHash = self::hashToken($token);
        $confirmKey = "security:confirm:{$sessionHash}:{$ip}";
        $codeTtl = (int) config('security.confirm_code_ttl', self::DEFAULT_CONFIRM_CODE_TTL);

        $existing = Redis::get($confirmKey);
        $country = $this->geoIpService->resolveCountryName($ip, $request);

        if ($existing) {
            $data = json_decode((string) $existing, true) ?: [];
            $code = $data['code'] ?? null;
        } else {
            $code = null;
        }

        if (! $code) {
            $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $payload = json_encode([
                'code' => $code,
                'token' => $token,
                'user_id' => $user->id,
                'ip' => $ip,
                'country' => $country,
                'created_at' => now()->timestamp,
            ]);

            Redis::setex($confirmKey, $codeTtl, (string) $payload);
            Redis::setex("security:confirm_index:{$sessionHash}", $codeTtl, $ip);
            Redis::setex("security:hash_to_token:{$sessionHash}", $codeTtl, $token);
            Redis::setex("security:hash_to_ip:{$sessionHash}", $codeTtl, $ip);
        } else {
            Redis::setex("security:hash_to_token:{$sessionHash}", $codeTtl, $token);
            Redis::setex("security:hash_to_ip:{$sessionHash}", $codeTtl, $ip);
        }

        // Throttled notification via Telegram bot
        $throttleKey = "security:alert_throttled:{$sessionHash}:{$ip}";
        $throttleTtl = (int) config('security.alert_throttle_ttl', self::DEFAULT_ALERT_THROTTLE_TTL);

        /** @var Connection $redis */
        $redis = Redis::connection();
        $canAlert = (bool) $redis->set($throttleKey, '1', 'EX', $throttleTtl, 'NX');
        if ($canAlert) {
            $userAgent = $request->userAgent() ?: 'Неизвестный браузер';
            $sent = $this->telegramService->sendSessionSecurityAlert(
                $user,
                $ip,
                $country,
                $userAgent,
                $code,
                $sessionHash,
                $user->hasCloudPassword()
            );

            if (! $sent) {
                Redis::del($throttleKey);
            }
        }

        return [
            'code' => $code,
            'session_hash' => $sessionHash,
            'country' => $country,
        ];
    }

    /**
     * Confirm a new IP address using the provided confirmation code.
     * Uses atomic Redis INCR to eliminate race condition attacks during code brute-forcing.
     *
     * @return array{success: bool, blocked: bool, attempts_left: int, error?: string}
     */
    public function confirmIp(string $token, string $code, string $ip): array
    {
        if ($this->isIpBlocked($ip)) {
            return [
                'success' => false,
                'blocked' => true,
                'attempts_left' => 0,
                'error' => 'IP-адрес временно заблокирован.',
            ];
        }

        $sessionHash = self::hashToken($token);
        $confirmKey = "security:confirm:{$sessionHash}:{$ip}";
        $attemptsKey = "security:attempts:{$sessionHash}:{$ip}";

        $pendingJson = Redis::get($confirmKey);
        if (! $pendingJson) {
            return [
                'success' => false,
                'blocked' => false,
                'attempts_left' => 0,
                'error' => 'Ключ подтверждения устарел или не был запрошен. Обновите страницу для получения нового ключа.',
            ];
        }

        $pendingData = json_decode((string) $pendingJson, true) ?: [];
        $expectedCode = (string) ($pendingData['code'] ?? '');
        $userId = (int) ($pendingData['user_id'] ?? 0);
        $user = User::find($userId);

        if (hash_equals($expectedCode, trim($code))) {
            // Success: add IP to verified_ips in session
            $this->addVerifiedIpToSession($token, $ip);
            Redis::del($confirmKey);
            Redis::del("security:confirm_index:{$sessionHash}");
            Redis::del($attemptsKey);

            if ($user) {
                ActionLog::create([
                    'actor_id' => $user->id,
                    'target_user_id' => $user->id,
                    'action_type' => ActionType::AUTH,
                    'field_name' => 'ip_verified',
                    'old_value' => null,
                    'new_value' => "Подтвержден вход с IP {$ip} (".($pendingData['country'] ?? 'Не определена').')',
                    'created_at' => now(),
                ]);
            }

            return [
                'success' => true,
                'blocked' => false,
                'attempts_left' => (int) config('security.max_failed_attempts', self::DEFAULT_MAX_ATTEMPTS),
            ];
        }

        // Invalid code: atomic counter against race conditions
        $maxAttempts = (int) config('security.max_failed_attempts', self::DEFAULT_MAX_ATTEMPTS);
        $codeTtl = (int) config('security.confirm_code_ttl', self::DEFAULT_CONFIRM_CODE_TTL);

        $attempts = (int) Redis::incr($attemptsKey);
        Redis::expire($attemptsKey, $codeTtl);

        if ($attempts >= $maxAttempts) {
            $blockDuration = (int) config('security.temp_block_duration', self::DEFAULT_TEMP_BLOCK_TTL);
            $this->blockIp($ip, $blockDuration, 'invalid_confirmation_token');
            Redis::del($confirmKey);
            Redis::del("security:confirm_index:{$sessionHash}");
            Redis::del($attemptsKey);

            if ($user) {
                $country = $pendingData['country'] ?? 'Не определена';
                $this->telegramService->sendFailedIpAttemptsAlert($user, $ip, $country);

                ActionLog::create([
                    'actor_id' => $user->id,
                    'target_user_id' => $user->id,
                    'action_type' => ActionType::AUTH,
                    'field_name' => 'ip_blocked',
                    'old_value' => null,
                    'new_value' => "Временная блокировка IP {$ip} после {$attempts} неверных попыток ввода ключа",
                    'created_at' => now(),
                ]);
            }

            return [
                'success' => false,
                'blocked' => true,
                'attempts_left' => 0,
                'error' => "Неверный ключ подтверждения. Превышено количество попыток. IP временно заблокирован на {$blockDuration} секунд.",
            ];
        }

        $attemptsLeft = max(0, $maxAttempts - $attempts);

        return [
            'success' => false,
            'blocked' => false,
            'attempts_left' => $attemptsLeft,
            'error' => "Неверный ключ подтверждения. Осталось попыток: {$attemptsLeft}.",
        ];
    }

    /**
     * Verify cloud password for the session and add the IP to verified_ips upon success.
     *
     * @return array{success: bool, blocked: bool, attempts_left: int, error?: string}
     */
    public function verifyCloudPassword(User $user, string $token, string $password, string $ip): array
    {
        if ($this->isIpBlocked($ip)) {
            return [
                'success' => false,
                'blocked' => true,
                'attempts_left' => 0,
                'error' => 'IP-адрес временно заблокирован.',
            ];
        }

        if (! $user->hasCloudPassword()) {
            return [
                'success' => false,
                'blocked' => false,
                'attempts_left' => 0,
                'error' => 'Облачный пароль не установлен.',
            ];
        }

        $sessionHash = self::hashToken($token);
        $userAttemptsKey = "security:attempts:pwd:user:{$user->id}:{$ip}";
        $ipAttemptsKey = "security:attempts:pwd:ip:{$ip}";
        $maxAttempts = (int) config('security.max_failed_attempts', self::DEFAULT_MAX_ATTEMPTS);
        $codeTtl = (int) config('security.confirm_code_ttl', self::DEFAULT_CONFIRM_CODE_TTL);

        if ($user->verifyCloudPassword(trim($password))) {
            $this->addVerifiedIpToSession($token, $ip);
            Redis::del($userAttemptsKey);
            Redis::del($ipAttemptsKey);
            Redis::del("security:attempts:{$sessionHash}:{$ip}");
            Redis::del("security:confirm:{$sessionHash}:{$ip}");
            Redis::del("security:confirm_index:{$sessionHash}");

            ActionLog::create([
                'actor_id' => $user->id,
                'target_user_id' => $user->id,
                'action_type' => ActionType::AUTH,
                'field_name' => 'cloud_password_verified',
                'old_value' => null,
                'new_value' => "Успешный ввод облачного пароля с IP {$ip}",
                'created_at' => now(),
            ]);

            return [
                'success' => true,
                'blocked' => false,
                'attempts_left' => $maxAttempts,
            ];
        }

        // Invalid password: increment atomic attempts counters
        $userAttempts = (int) Redis::incr($userAttemptsKey);
        Redis::expire($userAttemptsKey, $codeTtl);

        $ipAttempts = (int) Redis::incr($ipAttemptsKey);
        Redis::expire($ipAttemptsKey, $codeTtl);

        Redis::incr("security:attempts:{$sessionHash}:{$ip}");
        Redis::expire("security:attempts:{$sessionHash}:{$ip}", $codeTtl);

        $attempts = max($userAttempts, (int) Redis::get("security:attempts:{$sessionHash}:{$ip}"));

        if ($attempts >= $maxAttempts || $ipAttempts >= 10) {
            $blockDuration = (int) config('security.temp_block_duration', self::DEFAULT_TEMP_BLOCK_TTL);
            $this->blockIp($ip, $blockDuration, 'invalid_cloud_password');
            Redis::del($userAttemptsKey);
            Redis::del($ipAttemptsKey);
            Redis::del("security:attempts:{$sessionHash}:{$ip}");

            $country = $this->geoIpService->resolveCountryName($ip);
            $this->telegramService->sendFailedIpAttemptsAlert($user, $ip, $country);

            ActionLog::create([
                'actor_id' => $user->id,
                'target_user_id' => $user->id,
                'action_type' => ActionType::AUTH,
                'field_name' => 'ip_blocked',
                'old_value' => null,
                'new_value' => "Временная блокировка IP {$ip} после {$attempts} неверных попыток ввода облачного пароля",
                'created_at' => now(),
            ]);

            return [
                'success' => false,
                'blocked' => true,
                'attempts_left' => 0,
                'error' => "Неверный облачный пароль. Превышено количество попыток. IP временно заблокирован на {$blockDuration} секунд.",
            ];
        }

        $attemptsLeft = max(0, $maxAttempts - $attempts);

        return [
            'success' => false,
            'blocked' => false,
            'attempts_left' => $attemptsLeft,
            'error' => "Неверный облачный пароль. Осталось попыток: {$attemptsLeft}.",
        ];
    }

    /**
     * Check if a specific IP is verified for the given session token.
     */
    public function isIpVerifiedForSession(string $token, string $ip): bool
    {
        $sessionJson = Redis::get("session:{$token}") ?: Redis::get("session_data:{$token}");
        if (! $sessionJson) {
            return false;
        }

        $sessionData = json_decode((string) $sessionJson, true) ?: [];
        $verifiedIps = $sessionData['verified_ips'] ?? [];
        if (! is_array($verifiedIps)) {
            $verifiedIps = [];
        }

        if (! empty($sessionData['ip']) && ! in_array($sessionData['ip'], $verifiedIps, true)) {
            $verifiedIps[] = $sessionData['ip'];
        }

        return in_array($ip, $verifiedIps, true);
    }

    /**
     * Check if a password is too trivial (repeated chars or common sequences).
     */
    public function isPasswordTooTrivial(string $password): bool
    {
        $password = trim($password);
        if (mb_strlen($password) < 4) {
            return true;
        }

        // All identical characters: e.g. "0000", "1111", "aaaa"
        if (preg_match('/^(.)\1+$/u', $password)) {
            return true;
        }

        // Common sequences
        $trivial = [
            '1234', '12345', '123456', '12345678',
            '4321', '54321', '654321',
            '0123', '9876', '987654',
            'qwerty', 'password', 'admin',
        ];

        return in_array(strtolower($password), $trivial, true);
    }

    /**
     * Setup initial cloud password for user.
     *
     * @return array{success: bool, error?: string}
     */
    public function setupCloudPassword(User $user, string $password, ?string $token = null, ?string $ip = null): array
    {
        if ($user->hasCloudPassword()) {
            return [
                'success' => false,
                'error' => 'Облачный пароль уже установлен. Используйте форму смены пароля.',
            ];
        }

        $minLen = (int) config('security.cloud_password_min_length', 4);
        $maxLen = (int) config('security.cloud_password_max_length', 64);
        $password = trim($password);

        if (mb_strlen($password) < $minLen || mb_strlen($password) > $maxLen) {
            return [
                'success' => false,
                'error' => "Пароль должен содержать от {$minLen} до {$maxLen} символов.",
            ];
        }

        if ($this->isPasswordTooTrivial($password)) {
            return [
                'success' => false,
                'error' => 'Пароль слишком простой. Не используйте одинаковые символы или простые последовательности (например, 1111 или 1234).',
            ];
        }

        $user->setCloudPassword($password);

        if ($token && $ip) {
            $this->addVerifiedIpToSession($token, $ip);
            $sessionHash = self::hashToken($token);
            Redis::del("security:confirm:{$sessionHash}:{$ip}");
            Redis::del("security:confirm_index:{$sessionHash}");
            Redis::del("security:attempts:{$sessionHash}:{$ip}");
            Redis::del("security:attempts:pwd:user:{$user->id}:{$ip}");
            Redis::del("security:attempts:pwd:ip:{$ip}");
        }

        ActionLog::create([
            'actor_id' => $user->id,
            'target_user_id' => $user->id,
            'action_type' => ActionType::AUTH,
            'field_name' => 'cloud_password_set',
            'old_value' => null,
            'new_value' => 'Установлен облачный пароль'.($ip ? " с IP {$ip}" : ''),
            'created_at' => now(),
        ]);

        return ['success' => true];
    }

    /**
     * Change user's existing cloud password.
     *
     * @return array{success: bool, error?: string}
     */
    public function changeCloudPassword(User $user, string $currentPassword, string $newPassword, ?string $ip = null): array
    {
        if (! $user->hasCloudPassword()) {
            return $this->setupCloudPassword($user, $newPassword, null, $ip);
        }

        if ($ip && $this->isIpBlocked($ip)) {
            return [
                'success' => false,
                'error' => 'IP-адрес временно заблокирован.',
            ];
        }

        if (! $user->verifyCloudPassword(trim($currentPassword))) {
            if ($ip) {
                $userAttemptsKey = "security:attempts:pwd:user:{$user->id}:{$ip}";
                $maxAttempts = (int) config('security.max_failed_attempts', self::DEFAULT_MAX_ATTEMPTS);
                $codeTtl = (int) config('security.confirm_code_ttl', self::DEFAULT_CONFIRM_CODE_TTL);
                $attempts = (int) Redis::incr($userAttemptsKey);
                Redis::expire($userAttemptsKey, $codeTtl);

                if ($attempts >= $maxAttempts) {
                    $blockDuration = (int) config('security.temp_block_duration', self::DEFAULT_TEMP_BLOCK_TTL);
                    $this->blockIp($ip, $blockDuration, 'invalid_cloud_password');
                    Redis::del($userAttemptsKey);

                    return [
                        'success' => false,
                        'error' => "Текущий облачный пароль указан неверно. Превышено количество попыток. IP заблокирован на {$blockDuration} секунд.",
                    ];
                }
            }

            return [
                'success' => false,
                'error' => 'Текущий облачный пароль указан неверно.',
            ];
        }

        $minLen = (int) config('security.cloud_password_min_length', 4);
        $maxLen = (int) config('security.cloud_password_max_length', 64);
        $newPassword = trim($newPassword);

        if (mb_strlen($newPassword) < $minLen || mb_strlen($newPassword) > $maxLen) {
            return [
                'success' => false,
                'error' => "Новый пароль должен содержать от {$minLen} до {$maxLen} символов.",
            ];
        }

        if ($this->isPasswordTooTrivial($newPassword)) {
            return [
                'success' => false,
                'error' => 'Новый пароль слишком простой. Не используйте одинаковые символы или простые последовательности (например, 1111 или 1234).',
            ];
        }

        $user->setCloudPassword($newPassword);

        if ($ip) {
            Redis::del("security:attempts:pwd:user:{$user->id}:{$ip}");
            Redis::del("security:attempts:pwd:ip:{$ip}");
        }

        ActionLog::create([
            'actor_id' => $user->id,
            'target_user_id' => $user->id,
            'action_type' => ActionType::AUTH,
            'field_name' => 'cloud_password_changed',
            'old_value' => null,
            'new_value' => 'Облачный пароль изменен',
            'created_at' => now(),
        ]);

        return ['success' => true];
    }

    /**
     * Reset cloud password by administrator.
     *
     * @return array{success: bool}
     */
    public function adminResetCloudPassword(User $targetUser, User $actor): array
    {
        $targetUser->resetCloudPassword();

        ActionLog::create([
            'actor_id' => $actor->id,
            'target_user_id' => $targetUser->id,
            'action_type' => ActionType::AUTH,
            'field_name' => 'cloud_password_reset',
            'old_value' => null,
            'new_value' => "Облачный пароль сброшен администратором {$actor->system_name}",
            'created_at' => now(),
        ]);

        return ['success' => true];
    }

    /**
     * Approve verification directly via Telegram bot callback.
     *
     * @return array{success: bool, ip?: string, country?: string, error?: string}
     */
    public function approveByHash(string $sessionHash, string $code, ?User $actor = null): array
    {
        $ip = Redis::get("security:confirm_index:{$sessionHash}");
        if (! $ip) {
            return ['success' => false, 'error' => 'Запрос на подтверждение устарел или уже выполнен.'];
        }

        $confirmKey = "security:confirm:{$sessionHash}:{$ip}";
        $pendingJson = Redis::get($confirmKey);
        if (! $pendingJson) {
            return ['success' => false, 'error' => 'Данные подтверждения не найдены.'];
        }

        $pendingData = json_decode((string) $pendingJson, true) ?: [];
        $expectedCode = (string) ($pendingData['code'] ?? '');
        $token = (string) ($pendingData['token'] ?? '');
        $userId = (int) ($pendingData['user_id'] ?? 0);
        $user = User::find($userId);

        if (! hash_equals($expectedCode, trim($code))) {
            return ['success' => false, 'error' => 'Неверный ключ подтверждения.'];
        }

        $this->addVerifiedIpToSession($token, (string) $ip);
        Redis::del($confirmKey);
        Redis::del("security:confirm_index:{$sessionHash}");
        Redis::del("security:attempts:{$sessionHash}:{$ip}");

        $country = $pendingData['country'] ?? 'Не определена';

        if ($user) {
            ActionLog::create([
                'actor_id' => $actor?->id ?? $user->id,
                'target_user_id' => $user->id,
                'action_type' => ActionType::AUTH,
                'field_name' => 'ip_verified',
                'old_value' => null,
                'new_value' => "IP {$ip} ({$country}) подтвержден через Telegram бота",
                'created_at' => now(),
            ]);
        }

        return [
            'success' => true,
            'ip' => (string) $ip,
            'country' => $country,
        ];
    }

    /**
     * Terminate the session and block the offending IP address.
     *
     * @return array{success: bool, ip?: string, error?: string}
     */
    public function blockByHash(string $sessionHash, ?User $actor = null): array
    {
        $ip = Redis::get("security:confirm_index:{$sessionHash}")
            ?: Redis::get("security:hash_to_ip:{$sessionHash}");
        $confirmKey = $ip ? "security:confirm:{$sessionHash}:{$ip}" : null;
        $pendingJson = $confirmKey ? Redis::get($confirmKey) : null;

        $token = Redis::get("security:hash_to_token:{$sessionHash}");
        $userId = null;
        $country = 'Не определена';

        if ($pendingJson) {
            $pendingData = json_decode((string) $pendingJson, true) ?: [];
            $token = $token ?: ($pendingData['token'] ?? null);
            $userId = (int) ($pendingData['user_id'] ?? 0);
            $country = $pendingData['country'] ?? $country;
        }

        // Authorization check: actor must be session owner or an administrator
        if ($actor && $userId && (int) $actor->id !== (int) $userId && ! $actor->isOwner()) {
            return [
                'success' => false,
                'error' => 'У вас нет прав для блокировки этой сессии.',
            ];
        }

        $botBlockDuration = (int) config('security.bot_block_duration', self::DEFAULT_BOT_BLOCK_TTL);

        if ($ip) {
            $this->blockIp((string) $ip, $botBlockDuration, 'blocked_by_telegram_bot');
            Redis::del("security:confirm:{$sessionHash}:{$ip}");
            Redis::del("security:confirm_index:{$sessionHash}");
            Redis::del("security:hash_to_ip:{$sessionHash}");
            Redis::del("security:attempts:{$sessionHash}:{$ip}");
        }

        if ($token) {
            $this->sessionService->deleteSessionByToken($token);
            Redis::del("security:hash_to_token:{$sessionHash}");
        }

        $user = $userId ? User::find($userId) : $actor;

        if ($user) {
            ActionLog::create([
                'actor_id' => $actor?->id ?? $user->id,
                'target_user_id' => $user->id,
                'action_type' => ActionType::AUTH,
                'field_name' => 'session_blocked',
                'old_value' => null,
                'new_value' => 'Сессия отозвана, IP '.($ip ?: 'неизвестный')." ({$country}) заблокирован через Telegram-бота",
                'created_at' => now(),
            ]);
        }

        return [
            'success' => true,
            'ip' => (string) ($ip ?: 'неизвестный'),
        ];
    }

    /**
     * Add an IP address to the session's verified_ips array across all linked keys.
     */
    public function addVerifiedIpToSession(string $token, string $ip): void
    {
        $sessionJson = Redis::get("session:{$token}") ?: Redis::get("session_data:{$token}");
        if (! $sessionJson) {
            return;
        }

        $data = json_decode((string) $sessionJson, true) ?: [];
        $verifiedIps = $data['verified_ips'] ?? [];
        if (! is_array($verifiedIps)) {
            $verifiedIps = [];
        }

        if (! in_array($ip, $verifiedIps, true)) {
            $verifiedIps[] = $ip;
        }

        $data['verified_ips'] = $verifiedIps;

        $ttl = Redis::ttl("session:{$token}");
        if ($ttl <= 0) {
            $ttl = UserSessionService::SESSION_TTL;
        }

        $payload = (string) json_encode($data);
        Redis::setex("session:{$token}", $ttl, $payload);
        Redis::setex("session_data:{$token}", $ttl, $payload);

        // Also sync session:{$telegramUserId} if active
        $telegramUserId = $data['telegram_user_id'] ?? null;
        if ($telegramUserId) {
            $currentTgSession = Redis::get("session:{$telegramUserId}");
            if ($currentTgSession) {
                $tgData = json_decode((string) $currentTgSession, true) ?: [];
                if (($tgData['token'] ?? null) === $token) {
                    $tgData['verified_ips'] = $verifiedIps;
                    $tgTtl = Redis::ttl("session:{$telegramUserId}");
                    if ($tgTtl <= 0) {
                        $tgTtl = $ttl;
                    }
                    Redis::setex("session:{$telegramUserId}", $tgTtl, (string) json_encode($tgData));
                }
            }
        }
    }
}
