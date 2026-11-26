<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Security\GeoIpService;
use App\Services\Security\SessionSecurityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class ValidateTmaUser
{
    public function __construct(
        protected ?GeoIpService $geoIpService = null,
        protected ?SessionSecurityService $sessionSecurityService = null
    ) {}

    protected function geoIp(): GeoIpService
    {
        return $this->geoIpService ?? app(GeoIpService::class);
    }

    protected function securityService(): SessionSecurityService
    {
        return $this->sessionSecurityService ?? app(SessionSecurityService::class);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $clientIp = $this->geoIp()->resolveClientIp($request);

        if ($this->securityService()->isIpBlocked($clientIp)) {
            $remaining = $this->securityService()->getBlockRemainingSeconds($clientIp);
            Log::warning('Blocked IP access attempt', [
                'ip' => $clientIp,
                'path' => $request->path(),
                'remaining' => $remaining,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'ip_temporarily_blocked',
                    'message' => 'Доступ с вашего IP-адреса временно заблокирован из соображений безопасности.',
                    'blocked_until' => now()->addSeconds($remaining)->timestamp,
                ], 403);
            }

            return response()->view('tma.blocked', ['message' => 'Доступ с вашего IP-адреса временно заблокирован.'], 403);
        }

        if (! $request->expectsJson() && ! $request->is('api/*')) {
            if ($request->is('blocked')) {
                return $next($request);
            }

            $tokenParam = $request->query('token') ?? $request->query('session_token');
            $sessionId = is_string($tokenParam) && trim($tokenParam) !== ''
                ? trim($tokenParam)
                : ($request->hasSession() ? (string) $request->session()->get('tma_user_id') : '');

            if ($sessionId !== '') {
                $resolved = $this->resolveSessionAndUser("session_data:{$sessionId}");
                $user = $resolved['user'];
                $sessionData = $resolved['session_data'];

                if ($user && ! $user->is_active) {
                    Log::warning('TMA access blocked: inactive user', [
                        'user_id' => $user->id,
                        'ip' => $clientIp,
                    ]);

                    return response()->view('tma.blocked', ['message' => 'Ваш аккаунт был заблокирован. Обратитесь к владельцу системы.'], 403);
                }

                if ($user) {
                    $ipCheck = $this->securityService()->checkSessionIp($user, $sessionId, $clientIp, $request, $sessionData);
                    if (! $ipCheck['verified']) {
                        return response()->view('tma.auth.verify-ip', [
                            'ip' => $clientIp,
                            'country' => $ipCheck['country'] ?? 'Не определена 🌐',
                            'sessionToken' => $sessionId,
                            'hasCloudPassword' => (bool) ($ipCheck['has_cloud_password'] ?? $user->hasCloudPassword()),
                            'isNewIp' => true,
                        ], 403);
                    }

                    if (! $user->hasCloudPassword()) {
                        return response()->view('tma.auth.verify-ip', [
                            'ip' => $clientIp,
                            'country' => $this->geoIp()->resolveCountryName($clientIp, $request),
                            'sessionToken' => $sessionId,
                            'hasCloudPassword' => false,
                            'isNewIp' => false,
                        ], 403);
                    }

                    if ($request->hasSession()) {
                        if ($request->session()->get('tma_user_id') !== $sessionId) {
                            $request->session()->migrate(true);
                        }
                        $request->session()->put('tma_user_id', $sessionId);
                        $request->session()->put('tma_telegram_user_id', $user->telegram_user_id);
                        $request->session()->put('tma_role', $user->role->value);
                        $request->session()->put('tma_name', $user->system_name);
                    }

                    $request->attributes->set('user', $user);
                    Auth::setUser($user);

                    return $next($request);
                }
            }

            if ($sessionId === '' && Auth::user()) {
                $user = Auth::user();
                if (! $user->is_active) {
                    return response()->view('tma.blocked', ['message' => 'Ваш аккаунт был заблокирован. Обратитесь к владельцу системы.'], 403);
                }

                $request->attributes->set('user', $user);

                return $next($request);
            }

            // Never fall back to Auth::user() in production when the Redis session is missing or revoked.
            if (Auth::check()) {
                Auth::logout();
            }
            if ($request->hasSession() && $request->session()->has('tma_user_id')) {
                $request->session()->forget(['tma_user_id', 'tma_telegram_user_id', 'tma_role', 'tma_name']);
                $request->session()->invalidate();
            }

            if (! $this->isTmaEntryRoute($request)) {
                return response()->view('tma.blocked', [
                    'message' => 'Доступ разрешён только через Telegram Mini App. Откройте бота в Telegram для продолжения.',
                ], 403);
            }

            $request->attributes->set('user', null);

            return $next($request);
        }

        $token = $request->bearerToken();
        $sessionToken = $request->header('X-Session-Token');
        $effectiveToken = null;
        $sessionData = null;
        $user = null;

        if ($token) {
            $resolved = $this->resolveSessionAndUser("session:{$token}");
            $user = $resolved['user'];
            $sessionData = $resolved['session_data'];
            $effectiveToken = $token;
        } elseif ($sessionToken) {
            $resolved = $this->resolveSessionAndUser("session_data:{$sessionToken}");
            $user = $resolved['user'];
            $sessionData = $resolved['session_data'];
            $effectiveToken = $sessionToken;
        } elseif ($request->hasSession() && $request->session()->has('tma_user_id')) {
            $effectiveToken = (string) $request->session()->get('tma_user_id');
            $resolved = $this->resolveSessionAndUser("session_data:{$effectiveToken}");
            $user = $resolved['user'];
            $sessionData = $resolved['session_data'];
            if (! $user && Auth::check()) {
                Auth::logout();
            }
        } elseif (Auth::user()) {
            $user = Auth::user();
        } else {
            if (Auth::check()) {
                Auth::logout();
            }
            Log::warning('TMA API unauthorized', [
                'path' => $request->path(),
                'ip' => $clientIp,
                'has_bearer' => false,
                'has_session_token' => false,
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (! $user) {
            Log::warning('TMA API unauthorized: invalid token', [
                'path' => $request->path(),
                'ip' => $clientIp,
                'token_prefix' => substr($token ?? $sessionToken ?? '', 0, 8),
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (! $user->is_active) {
            Log::warning('TMA API access blocked: inactive user', [
                'user_id' => $user->id,
                'ip' => $clientIp,
            ]);

            return response()->json(['error' => 'Access blocked'], 403);
        }

        // Check IP verification for session unless logging out
        if ($effectiveToken && ! $request->is('api/auth/logout')) {
            $ipCheck = $this->securityService()->checkSessionIp($user, $effectiveToken, $clientIp, $request, $sessionData);

            if (! $ipCheck['verified']) {
                if (($ipCheck['status'] ?? '') === 'blocked') {
                    return response()->json([
                        'error' => 'ip_temporarily_blocked',
                        'message' => 'Доступ с вашего IP-адреса временно заблокирован.',
                    ], 403);
                }

                $hasCloudPassword = (bool) ($ipCheck['has_cloud_password'] ?? $user->hasCloudPassword());

                return response()->json([
                    'error' => 'ip_verification_required',
                    'message' => $hasCloudPassword
                        ? 'Обнаружен вход с нового IP-адреса. Требуется ввод облачного пароля.'
                        : 'Обнаружен вход с нового IP-адреса. Требуется установка облачного пароля.',
                    'ip' => $clientIp,
                    'country' => $ipCheck['country'] ?? 'Не определена 🌐',
                    'session_hash' => $ipCheck['session_hash'] ?? SessionSecurityService::hashToken($effectiveToken),
                    'has_cloud_password' => $hasCloudPassword,
                ], 403);
            }
        }

        $request->attributes->set('user', $user);
        Auth::setUser($user);

        return $next($request);
    }

    /**
     * Resolve user and decoded session data in a single Redis roundtrip.
     *
     * @return array{user: ?User, session_data: ?array<string, mixed>}
     */
    protected function resolveSessionAndUser(string $key): array
    {
        $sessionJson = Redis::get($key);

        if (! $sessionJson) {
            return ['user' => null, 'session_data' => null];
        }

        $data = json_decode((string) $sessionJson, true);
        if (! is_array($data) || ! isset($data['user_id'])) {
            return ['user' => null, 'session_data' => null];
        }

        $user = $this->findUserCached((int) $data['user_id']);

        return ['user' => $user, 'session_data' => $data];
    }

    protected function findUserCached(int $userId): ?User
    {
        return Cache::remember("user_cache:{$userId}", 300, function () use ($userId) {
            return User::find($userId);
        });
    }

    protected function isTmaEntryRoute(Request $request): bool
    {
        return $request->is('/')
            || $request->is('tma')
            || $request->is('tma/owner')
            || $request->is('tma/manager')
            || $request->is('tma/courier');
    }
}
