<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ActionType;
use App\Http\Controllers\Controller;
use App\Models\ActionLog;
use App\Models\User;
use App\Services\Security\GeoIpService;
use App\Services\Security\SessionSecurityService;
use App\Services\UserSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class SessionSecurityController extends Controller
{
    public const MAX_USER_SESSIONS = UserSessionService::MAX_USER_SESSIONS;

    public function __construct(
        protected UserSessionService $sessionService,
        protected SessionSecurityService $securityService,
        protected GeoIpService $geoIpService
    ) {}

    /**
     * Get active sessions for the authenticated user.
     */
    public function sessions(Request $request): JsonResponse
    {
        $user = $request->attributes->get('user') ?? auth()->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $currentToken = $request->bearerToken()
            ?? $request->header('X-Session-Token')
            ?? ($request->hasSession() ? $request->session()->get('tma_user_id') : null);

        $sessions = $this->sessionService->getUserSessions($user, $currentToken);

        return response()->json([
            'sessions' => $sessions,
            'max_sessions' => (int) config('security.max_user_sessions', self::MAX_USER_SESSIONS),
        ]);
    }

    /**
     * Revoke one or all other active sessions.
     */
    public function revokeSession(Request $request): JsonResponse
    {
        $user = $request->attributes->get('user') ?? auth()->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $currentToken = $request->bearerToken()
            ?? $request->header('X-Session-Token')
            ?? ($request->hasSession() ? $request->session()->get('tma_user_id') : null);

        $targetToken = $request->input('token');
        $revokeAll = $request->boolean('revoke_all', false);

        if ($revokeAll) {
            $revoked = $this->sessionService->revokeAllOtherSessions($user, $currentToken);

            ActionLog::create([
                'actor_id' => $user->id,
                'target_user_id' => $user->id,
                'action_type' => ActionType::AUTH,
                'field_name' => 'revoke_session',
                'old_value' => null,
                'new_value' => "Сброс всех остальных сессий (отозвано: {$revoked})",
                'created_at' => now(),
            ]);

            return response()->json(['status' => 'ok', 'revoked' => $revoked]);
        }

        if (! $targetToken) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        if ($targetToken === $currentToken || ($currentToken && hash_equals(hash('sha256', (string) $currentToken), (string) $targetToken))) {
            return response()->json(['error' => 'Cannot revoke current session'], 422);
        }

        $revoked = $this->sessionService->revokeSession($user, $targetToken);
        if (! $revoked) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        ActionLog::create([
            'actor_id' => $user->id,
            'target_user_id' => $user->id,
            'action_type' => ActionType::AUTH,
            'field_name' => 'revoke_session',
            'old_value' => null,
            'new_value' => 'Отзыв сессии',
            'created_at' => now(),
        ]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Emergency block of an unverified session and its IP.
     */
    public function blockSession(Request $request): JsonResponse
    {
        $token = trim((string) (
            $request->bearerToken()
            ?: $request->input('token')
            ?: ($request->hasSession() ? $request->session()->get('tma_user_id') : '')
        ));

        if (empty($token)) {
            return response()->json(['error' => 'Session token required'], 422);
        }

        $sessionJson = Redis::get("session:{$token}") ?: Redis::get("session_data:{$token}");
        $sessionData = $sessionJson ? json_decode((string) $sessionJson, true) : null;
        $sessionUser = is_array($sessionData) && isset($sessionData['user_id'])
            ? User::find((int) $sessionData['user_id'])
            : null;

        if (! $sessionUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $actor = $request->attributes->get('user') ?? auth()->user();
        if ($actor && (int) $actor->id !== (int) $sessionUser->id && ! $actor->isOwner()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $sessionHash = SessionSecurityService::hashToken($token);
        $result = $this->securityService->blockByHash($sessionHash, $actor ?? $sessionUser);
        if (! ($result['success'] ?? false)) {
            return response()->json(['error' => $result['error'] ?? 'Forbidden'], 403);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Сессия отозвана, доступ заблокирован.',
        ]);
    }

    /**
     * Confirm IP by verification code or cloud password.
     */
    public function confirmIp(Request $request): JsonResponse
    {
        $password = (string) ($request->input('password') ?? '');
        $code = (string) ($request->input('code') ?? $request->input('token_key') ?? '');
        $token = trim((string) (
            $request->bearerToken()
            ?: $request->input('token')
            ?: ($request->hasSession() ? $request->session()->get('tma_user_id') : '')
        ));

        if (empty($token)) {
            return response()->json([
                'error' => 'missing_parameters',
                'message' => 'Необходимо указать токен сессии.',
            ], 422);
        }

        $user = $request->attributes->get('user') ?? auth()->user() ?? $this->resolveUserFromToken($token);
        if (! $user) {
            return response()->json(['error' => 'User not found for session'], 404);
        }

        // If cloud password is provided or user has cloud password, use cloud password verification
        if (! empty($password) || ($user->hasCloudPassword() && ! empty($code) && strlen($code) !== 6)) {
            $inputPassword = ! empty($password) ? $password : $code;
            $clientIp = $this->geoIpService->resolveClientIp($request);
            $result = $this->securityService->verifyCloudPassword($user, $token, $inputPassword, $clientIp);

            if ($result['success']) {
                return response()->json([
                    'status' => 'ok',
                    'message' => 'Вход успешно подтвержден облачным паролем.',
                ]);
            }

            if ($result['blocked']) {
                return response()->json([
                    'error' => 'invalid_password',
                    'message' => $result['error'] ?? 'Неверный облачный пароль. IP временно заблокирован.',
                    'blocked' => true,
                ], 403);
            }

            return response()->json([
                'error' => 'invalid_password',
                'message' => $result['error'] ?? 'Неверный облачный пароль.',
                'attempts_left' => $result['attempts_left'] ?? 0,
                'blocked' => false,
            ], 422);
        }

        if (empty($code)) {
            return response()->json([
                'error' => 'missing_parameters',
                'message' => 'Необходимо указать ключ подтверждения или облачный пароль.',
            ], 422);
        }

        $clientIp = $this->geoIpService->resolveClientIp($request);
        $result = $this->securityService->confirmIp($token, $code, $clientIp);

        if ($result['success']) {
            return response()->json([
                'status' => 'ok',
                'message' => 'IP-адрес успешно подтвержден.',
            ]);
        }

        if ($result['blocked']) {
            return response()->json([
                'error' => 'invalid_code',
                'message' => $result['error'] ?? 'Неверный ключ подтверждения. IP временно заблокирован.',
                'blocked' => true,
            ], 403);
        }

        return response()->json([
            'error' => 'invalid_code',
            'message' => $result['error'] ?? 'Неверный ключ подтверждения.',
            'attempts_left' => $result['attempts_left'] ?? 0,
            'blocked' => false,
        ], 422);
    }

    /**
     * Resolve user from session token.
     */
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
