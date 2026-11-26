<?php

namespace App\Http\Controllers;

use App\Enums\ActionType;
use App\Enums\UserRole;
use App\Http\Requests\TmaAuthRequest;
use App\Models\ActionLog;
use App\Models\User;
use App\Services\Security\GeoIpService;
use App\Services\Security\SessionSecurityService;
use App\Services\Telegram\TelegramInitDataValidator;
use App\Services\TelegramService;
use App\Services\UserSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public const MAX_USER_SESSIONS = UserSessionService::MAX_USER_SESSIONS;

    public function __construct(
        protected TelegramInitDataValidator $telegramValidator,
        protected UserSessionService $sessionService,
        protected TelegramService $telegramService,
        protected ?GeoIpService $geoIpService = null,
        protected ?SessionSecurityService $securityService = null
    ) {
        $this->geoIpService = $geoIpService ?? app(GeoIpService::class);
        $this->securityService = $securityService ?? app(SessionSecurityService::class);
    }

    public function tma(TmaAuthRequest $request): JsonResponse
    {
        $clientIp = $this->geoIpService->resolveClientIp($request);
        if ($this->securityService->isIpBlocked($clientIp)) {
            $remaining = $this->securityService->getBlockRemainingSeconds($clientIp);

            return response()->json([
                'error' => 'ip_temporarily_blocked',
                'message' => 'Доступ с вашего IP-адреса временно заблокирован из соображений безопасности.',
                'blocked_until' => now()->addSeconds($remaining)->timestamp,
            ], 403);
        }

        $validated = $request->validated();

        try {
            $telegramId = $this->telegramValidator->getTelegramUserId($validated['initData']);
            $telegramUsername = null;
            try {
                $telegramUser = $this->telegramValidator->getTelegramUser($validated['initData']);
                $telegramUsername = $telegramUser->username ?? null;
            } catch (\Throwable) {
            }
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'Invalid initData'], 403);
        }

        $allowedUser = User::where('telegram_user_id', $telegramId)
            ->where('is_active', true)
            ->first();

        if (! $allowedUser) {
            Log::warning('TMA auth failed: user not allowed', [
                'telegram_id' => $telegramId,
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'User not allowed'], 403);
        }

        if ($telegramUsername && $allowedUser->telegram_username !== $telegramUsername) {
            $allowedUser->update(['telegram_username' => $telegramUsername]);
        }

        $providedToken = $validated['session_token'] ?? $request->bearerToken() ?? $request->header('X-Session-Token');

        $sessionToken = null;
        $isRefreshed = false;
        if ($providedToken && is_string($providedToken)) {
            $refreshed = $this->sessionService->refreshSession($providedToken, $allowedUser->id, $clientIp);
            if ($refreshed) {
                $sessionToken = $providedToken;
                $isRefreshed = true;
            }
        }

        if (! $sessionToken) {
            $sessionToken = Str::random(64);
            $country = $this->geoIpService->resolveCountryName($clientIp, $request);
            $this->sessionService->registerSession(
                $allowedUser,
                $sessionToken,
                $telegramId,
                UserSessionService::SESSION_TTL,
                $clientIp,
                $country,
                'tma'
            );
        }

        if ($request->hasSession()) {
            $request->session()->put('tma_user_id', $sessionToken);
            $request->session()->put('tma_telegram_user_id', $telegramId);
            $request->session()->put('tma_role', $allowedUser->role->value);
            $request->session()->put('tma_name', $allowedUser->system_name);
            $request->session()->migrate(true);
        }

        Log::info('TMA auth success', [
            'user_id' => $allowedUser->id,
            'telegram_id' => $telegramId,
            'ip' => $request->ip(),
            'refreshed' => $isRefreshed,
        ]);

        ActionLog::create([
            'actor_id' => $allowedUser->id,
            'target_user_id' => $allowedUser->id,
            'action_type' => ActionType::AUTH,
            'field_name' => 'login',
            'old_value' => null,
            'new_value' => $isRefreshed
                ? "TMA вход (продление сессии, Telegram ID: {$telegramId})"
                : "TMA вход (Telegram ID: {$telegramId})",
            'created_at' => now(),
        ]);

        return response()->json([
            'id' => $allowedUser->id,
            'role' => $allowedUser->role->value,
            'system_name' => $allowedUser->system_name,
            'telegram_user_id' => $telegramId,
            'session_token' => $sessionToken,
            'has_cloud_password' => $allowedUser->hasCloudPassword(),
            'cloud_password_set_at' => $allowedUser->cloud_password_set_at?->toIso8601String(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->attributes->get('user') ?? auth()->user();
        $sessionToken = $request->bearerToken()
            ?? $request->header('X-Session-Token')
            ?? ($request->hasSession() ? $request->session()->get('tma_user_id') : null)
            ?? $request->input('session_token');

        $this->sessionService->logout($user, $sessionToken);

        Auth::logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($user) {
            ActionLog::create([
                'actor_id' => $user->id,
                'target_user_id' => $user->id,
                'action_type' => ActionType::AUTH,
                'field_name' => 'logout',
                'old_value' => null,
                'new_value' => 'Выход из системы',
                'created_at' => now(),
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->attributes->get('user') ?? auth()->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'id' => $user->id,
            'role' => $user->role->value,
            'system_name' => $user->system_name,
            'telegram_user_id' => $user->telegram_user_id,
            'has_cloud_password' => $user->hasCloudPassword(),
            'cloud_password_set_at' => $user->cloud_password_set_at?->toIso8601String(),
        ]);
    }

    public function syncSession(Request $request): JsonResponse
    {
        $user = $request->attributes->get('user') ?? auth()->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $token = $request->bearerToken()
            ?? $request->header('X-Session-Token')
            ?? (string) $request->input('session_token');

        if ($token && $request->hasSession()) {
            $request->session()->put('tma_user_id', $token);
            $request->session()->put('tma_telegram_user_id', $user->telegram_user_id);
            $request->session()->put('tma_role', $user->role->value);
            $request->session()->put('tma_name', $user->system_name);
            $request->session()->migrate(true);
        }

        return response()->json([
            'id' => $user->id,
            'role' => $user->role->value,
            'system_name' => $user->system_name,
            'telegram_user_id' => $user->telegram_user_id,
            'has_cloud_password' => $user->hasCloudPassword(),
            'cloud_password_set_at' => $user->cloud_password_set_at?->toIso8601String(),
        ]);
    }

    public function createDesktopLink(Request $request): JsonResponse
    {
        $user = $request->attributes->get('user') ?? auth()->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $token = $this->sessionService->createDesktopToken($user->id);
        $baseUrl = rtrim((string) config('nutgram.webapp_url', config('app.url')), '/');
        $url = "{$baseUrl}/tma/login/desktop?token={$token}";

        $sent = false;
        if ($request->boolean('send_to_telegram')) {
            $sent = $this->telegramService->sendDesktopLoginLink($user, $url);
        }

        ActionLog::create([
            'actor_id' => $user->id,
            'target_user_id' => $user->id,
            'action_type' => ActionType::AUTH,
            'field_name' => 'desktop_link',
            'old_value' => null,
            'new_value' => $sent ? 'Создана ссылка для входа с ПК (отправлена в Telegram)' : 'Создана ссылка для входа с ПК',
            'created_at' => now(),
        ]);

        return response()->json([
            'url' => $url,
            'expires_in' => UserSessionService::DESKTOP_AUTH_TTL,
            'sent_to_telegram' => $sent,
        ]);
    }

    public function desktopLogin(Request $request): Response
    {
        $clientIp = $this->geoIpService->resolveClientIp($request);
        if ($this->securityService->isIpBlocked($clientIp)) {
            return response()->view('tma.blocked', ['message' => 'Доступ с вашего IP-адреса временно заблокирован.'], 403);
        }

        $token = (string) $request->query('token');

        if (! $token) {
            return response()->view('tma.auth.desktop-error', ['message' => 'Не указан токен входа.'], 400);
        }

        $userId = $this->sessionService->consumeDesktopToken($token);

        if (! $userId) {
            return response()->view('tma.auth.desktop-error', ['message' => 'Ссылка устарела или уже была использована. Запросите новую ссылку в приложении Telegram.'], 403);
        }

        $user = User::where('id', $userId)->where('is_active', true)->first();

        if (! $user) {
            return response()->view('tma.auth.desktop-error', ['message' => 'Пользователь не найден или заблокирован.'], 403);
        }

        $sessionToken = Str::random(64);
        $country = $this->geoIpService->resolveCountryName($clientIp, $request);
        $this->sessionService->registerSession(
            $user,
            $sessionToken,
            $user->telegram_user_id,
            UserSessionService::SESSION_TTL,
            $clientIp,
            $country,
            'desktop'
        );

        if ($request->hasSession()) {
            $request->session()->put('tma_user_id', $sessionToken);
            $request->session()->put('tma_telegram_user_id', $user->telegram_user_id);
            $request->session()->put('tma_role', $user->role->value);
            $request->session()->put('tma_name', $user->system_name);
            $request->session()->migrate(true);
        }

        $redirectPath = match ($user->role) {
            UserRole::MANAGER => '/tma/manager',
            UserRole::COURIER => '/tma/courier',
            default => '/tma',
        };

        ActionLog::create([
            'actor_id' => $user->id,
            'target_user_id' => $user->id,
            'action_type' => ActionType::AUTH,
            'field_name' => 'desktop_login',
            'old_value' => null,
            'new_value' => 'Вход по ссылке с компьютера',
            'created_at' => now(),
        ]);

        return response()->view('tma.auth.desktop-success', [
            'user' => $user,
            'sessionToken' => $sessionToken,
            'redirectPath' => $redirectPath,
        ]);
    }

    protected function resolveUserFromToken(string $token): ?User
    {
        $sessionJson = Redis::get("session:{$token}") ?: Redis::get("session_data:{$token}");
        if (! $sessionJson) {
            return null;
        }

        $data = json_decode((string) $sessionJson, true);
        $userId = $data['user_id'] ?? null;

        return $userId ? User::find((int) $userId) : null;
    }
}
