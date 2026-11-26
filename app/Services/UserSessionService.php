<?php

namespace App\Services;

use App\Models\User;
use App\Services\Security\GeoIpService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class UserSessionService
{
    public const MAX_USER_SESSIONS = 10;

    public const MAX_DESKTOP_SESSIONS = 5;

    public const MAX_TMA_SESSIONS = 3;

    public const SESSION_TTL = 86400 * 30; // 30 days

    public const DESKTOP_AUTH_TTL = 300; // 5 minutes

    public function registerSession(
        User $user,
        string $sessionToken,
        ?int $telegramId = null,
        int $ttl = self::SESSION_TTL,
        ?string $ip = null,
        ?string $country = null,
        string $deviceType = 'tma'
    ): void {
        $telegramId = $telegramId ?? $user->telegram_user_id;
        $sessionKey = "session:{$telegramId}";
        $sessionDataKey = "session_data:{$sessionToken}";
        $bearerKey = "session:{$sessionToken}";
        $now = now()->timestamp;

        $ip = $ip ?: (request()?->ip() ?: '127.0.0.1');
        if (! $country && class_exists(GeoIpService::class)) {
            try {
                $country = app(GeoIpService::class)->resolveCountryName($ip);
            } catch (\Throwable) {
                $country = 'Локальная сеть 🏠';
            }
        }

        $sessionPayload = json_encode([
            'user_id' => $user->id,
            'telegram_user_id' => $telegramId,
            'role' => $user->role->value,
            'token' => $sessionToken,
            'device_type' => $deviceType,
            'created_at' => $now,
            'last_active_at' => $now,
            'ip' => $ip,
            'country' => $country,
            'verified_ips' => [$ip],
        ]);

        if ($deviceType !== 'desktop') {
            Redis::setex($sessionKey, $ttl, $sessionPayload);
        }
        Redis::setex($sessionDataKey, $ttl, $sessionPayload);
        Redis::setex($bearerKey, $ttl, $sessionPayload);

        Redis::sadd("user_sessions:{$user->id}", $sessionToken);

        $this->pruneUserSessions($user->id);
    }

    public function refreshSession(
        string $token,
        int $userId,
        ?string $ip = null,
        int $ttl = self::SESSION_TTL,
        ?string $expectedDeviceType = 'tma'
    ): ?array {
        $sessionJson = Redis::get("session:{$token}") ?: Redis::get("session_data:{$token}");
        if (! $sessionJson) {
            return null;
        }

        $decoded = json_decode((string) $sessionJson, true) ?: [];
        if ((int) ($decoded['user_id'] ?? 0) !== $userId) {
            return null;
        }

        $deviceType = $decoded['device_type'] ?? 'tma';
        if ($expectedDeviceType !== null && $deviceType !== $expectedDeviceType) {
            return null;
        }

        $decoded['last_active_at'] = now()->timestamp;

        if ($ip) {
            $verifiedIps = $decoded['verified_ips'] ?? [];
            if (! is_array($verifiedIps)) {
                $verifiedIps = [];
            }
            if (! in_array($ip, $verifiedIps, true)) {
                $verifiedIps[] = $ip;
            }
            $decoded['verified_ips'] = $verifiedIps;
            $decoded['ip'] = $ip;
        }

        $encoded = json_encode($decoded);
        Redis::setex("session:{$token}", $ttl, $encoded);
        Redis::setex("session_data:{$token}", $ttl, $encoded);

        $telegramUserId = $decoded['telegram_user_id'] ?? null;
        if ($telegramUserId) {
            $currentTgSession = Redis::get("session:{$telegramUserId}");
            if ($currentTgSession) {
                $tgData = json_decode((string) $currentTgSession, true) ?: [];
                if (($tgData['token'] ?? null) === $token) {
                    Redis::setex("session:{$telegramUserId}", $ttl, $encoded);
                }
            }
        }

        return $decoded;
    }

    public function logout(?User $user, ?string $sessionToken): void
    {
        if ($sessionToken) {
            $userId = $user?->id;
            $telegramUserId = $user?->telegram_user_id;

            if (! $userId || ! $telegramUserId) {
                $sessionJson = Redis::get("session:{$sessionToken}") ?: Redis::get("session_data:{$sessionToken}");
                if ($sessionJson) {
                    $decoded = json_decode((string) $sessionJson, true);
                    $userId = $userId ?: ($decoded['user_id'] ?? null);
                    $telegramUserId = $telegramUserId ?: ($decoded['telegram_user_id'] ?? null);
                }
            }

            if ($userId) {
                Redis::srem("user_sessions:{$userId}", $sessionToken);
            }

            if ($telegramUserId) {
                $currentTgSession = Redis::get("session:{$telegramUserId}");
                if ($currentTgSession) {
                    $currentTgData = json_decode((string) $currentTgSession, true);
                    if (($currentTgData['token'] ?? null) === $sessionToken) {
                        Redis::del("session:{$telegramUserId}");
                    }
                }
            }

            Redis::del(["session_data:{$sessionToken}", "session:{$sessionToken}"]);
        } elseif ($user) {
            $telegramId = $user->telegram_user_id;
            if ($telegramId) {
                Redis::del("session:{$telegramId}");
            }
        }
    }

    public function getUserSessions(User $user, ?string $currentToken = null): array
    {
        $tokenSet = Redis::smembers("user_sessions:{$user->id}");
        $sessions = [];

        foreach ($tokenSet as $token) {
            $dataJson = Redis::get("session:{$token}") ?: Redis::get("session_data:{$token}");
            if (! $dataJson) {
                Redis::srem("user_sessions:{$user->id}", $token);

                continue;
            }

            $decoded = json_decode($dataJson, true) ?: [];
            $ttl = Redis::ttl("session:{$token}");
            if ($ttl === -2) {
                $ttl = Redis::ttl("session_data:{$token}");
            }

            $sessionHash = hash('sha256', $token);

            $sessions[] = [
                'id' => $sessionHash,
                'token_hint' => substr($token, 0, 6).'...'.substr($token, -4),
                'token' => $sessionHash,
                'is_current' => $token === $currentToken,
                'device_type' => $decoded['device_type'] ?? 'tma',
                'expires_in' => $ttl > 0 ? $ttl : null,
                'created_at' => $decoded['created_at'] ?? null,
                'last_active_at' => $decoded['last_active_at'] ?? null,
                'ip' => $decoded['ip'] ?? null,
                'country' => $decoded['country'] ?? null,
            ];
        }

        usort($sessions, fn ($a, $b) => (int) $b['is_current'] - (int) $a['is_current']);

        return $sessions;
    }

    public function revokeSession(User $user, string $targetIdentifier): bool
    {
        $tokenSet = Redis::smembers("user_sessions:{$user->id}");
        $targetToken = null;

        foreach ($tokenSet as $token) {
            if ($token === $targetIdentifier || hash_equals(hash('sha256', $token), $targetIdentifier)) {
                $targetToken = $token;
                break;
            }
        }

        if (! $targetToken) {
            return false;
        }

        $this->deleteSessionKeys($user->id, $targetToken);

        return true;
    }

    public function revokeAllOtherSessions(User $user, ?string $currentToken): int
    {
        $tokenSet = Redis::smembers("user_sessions:{$user->id}");
        $revokedCount = 0;

        foreach ($tokenSet as $token) {
            if ($token === $currentToken) {
                continue;
            }

            $this->deleteSessionKeys($user->id, $token);
            $revokedCount++;
        }

        return $revokedCount;
    }

    public function pruneUserSessions(int $userId, ?int $maxAllowed = null): void
    {
        $tokenSet = Redis::smembers("user_sessions:{$userId}");
        if (empty($tokenSet)) {
            return;
        }

        $maxTotal = $maxAllowed ?? (int) config('security.max_user_sessions', self::MAX_USER_SESSIONS);
        $maxDesktop = (int) config('security.max_desktop_sessions', self::MAX_DESKTOP_SESSIONS);
        $maxTma = (int) config('security.max_tma_sessions', self::MAX_TMA_SESSIONS);

        $tmaSessions = [];
        $desktopSessions = [];

        foreach ($tokenSet as $token) {
            $dataJson = Redis::get("session:{$token}") ?: Redis::get("session_data:{$token}");
            if (! $dataJson) {
                Redis::srem("user_sessions:{$userId}", $token);

                continue;
            }

            $decoded = json_decode((string) $dataJson, true) ?: [];
            $ttl = Redis::ttl("session:{$token}");
            if ($ttl === -2) {
                $ttl = Redis::ttl("session_data:{$token}");
            }

            $deviceType = $decoded['device_type'] ?? 'tma';
            $sessionItem = [
                'token' => $token,
                'device_type' => $deviceType,
                'created_at' => $decoded['created_at'] ?? ($ttl > 0 ? (self::SESSION_TTL - $ttl) : 0),
            ];

            if ($deviceType === 'desktop') {
                $desktopSessions[] = $sessionItem;
            } else {
                $tmaSessions[] = $sessionItem;
            }
        }

        usort($tmaSessions, fn ($a, $b) => $a['created_at'] <=> $b['created_at']);
        usort($desktopSessions, fn ($a, $b) => $a['created_at'] <=> $b['created_at']);

        // Prune excess TMA sessions (never touches desktop sessions)
        if (count($tmaSessions) > $maxTma) {
            $overflow = count($tmaSessions) - $maxTma;
            $toPrune = array_splice($tmaSessions, 0, $overflow);
            foreach ($toPrune as $item) {
                $this->deleteSessionKeys($userId, $item['token']);
            }
        }

        // Prune excess Desktop sessions (never touches TMA sessions)
        if (count($desktopSessions) > $maxDesktop) {
            $overflow = count($desktopSessions) - $maxDesktop;
            $toPrune = array_splice($desktopSessions, 0, $overflow);
            foreach ($toPrune as $item) {
                $this->deleteSessionKeys($userId, $item['token']);
            }
        }

        // Check total pool limit if still exceeded
        $remaining = array_merge($tmaSessions, $desktopSessions);
        if (count($remaining) > $maxTotal) {
            usort($remaining, fn ($a, $b) => $a['created_at'] <=> $b['created_at']);
            $overflow = count($remaining) - $maxTotal;
            $toPrune = array_slice($remaining, 0, $overflow);
            foreach ($toPrune as $item) {
                $this->deleteSessionKeys($userId, $item['token']);
            }
        }
    }

    public function createDesktopToken(int $userId): string
    {
        $token = Str::random(64);
        Redis::setex("desktop_auth:{$token}", self::DESKTOP_AUTH_TTL, json_encode([
            'user_id' => $userId,
            'created_at' => now()->timestamp,
        ]));

        return $token;
    }

    public function consumeDesktopToken(string $token): ?int
    {
        $cacheKey = "desktop_auth:{$token}";
        $dataJson = Redis::command('getdel', [$cacheKey]);

        if (! $dataJson) {
            return null;
        }

        $data = json_decode((string) $dataJson, true);

        return isset($data['user_id']) ? (int) $data['user_id'] : null;
    }

    public function deleteSessionByToken(string $token): bool
    {
        $dataJson = Redis::get("session:{$token}") ?: Redis::get("session_data:{$token}");
        if (! $dataJson) {
            return false;
        }

        $decoded = json_decode((string) $dataJson, true) ?: [];
        $userId = $decoded['user_id'] ?? null;
        if (! $userId) {
            Redis::del("session:{$token}");
            Redis::del("session_data:{$token}");

            return true;
        }

        $this->deleteSessionKeys((int) $userId, $token);

        return true;
    }

    public function deleteSessionKeys(int $userId, string $token): void
    {
        $dataJson = Redis::get("session:{$token}") ?: Redis::get("session_data:{$token}");
        if ($dataJson) {
            $decoded = json_decode($dataJson, true);
            $telegramUserId = $decoded['telegram_user_id'] ?? null;
            if ($telegramUserId) {
                $currentTgSession = Redis::get("session:{$telegramUserId}");
                if ($currentTgSession) {
                    $currentTgData = json_decode($currentTgSession, true);
                    if (($currentTgData['token'] ?? null) === $token) {
                        Redis::del("session:{$telegramUserId}");
                    }
                }
            }
        }

        Redis::del(["session_data:{$token}", "session:{$token}"]);
        Redis::srem("user_sessions:{$userId}", $token);
    }
}
