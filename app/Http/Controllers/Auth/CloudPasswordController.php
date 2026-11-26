<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\GeoIpService;
use App\Services\Security\SessionSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class CloudPasswordController extends Controller
{
    public function __construct(
        protected SessionSecurityService $securityService,
        protected GeoIpService $geoIpService
    ) {}

    /**
     * Verify cloud password when logging in or entering from an unverified IP.
     */
    public function verify(Request $request): JsonResponse
    {
        $password = (string) $request->input('password', '');
        $token = trim((string) (
            $request->bearerToken()
            ?: $request->input('token')
            ?: ($request->hasSession() ? $request->session()->get('tma_user_id') : '')
        ));

        if (empty($password) || empty($token)) {
            return response()->json([
                'error' => 'missing_parameters',
                'message' => 'Необходимо указать пароль и токен сессии.',
            ], 422);
        }

        $user = $request->attributes->get('user') ?? auth()->user() ?? $this->resolveUserFromToken($token);
        if (! $user) {
            return response()->json(['error' => 'User not found for session'], 404);
        }

        $clientIp = $this->geoIpService->resolveClientIp($request);
        $result = $this->securityService->verifyCloudPassword($user, $token, $password, $clientIp);

        if ($result['success']) {
            return response()->json([
                'status' => 'ok',
                'message' => 'Облачный пароль подтвержден.',
            ]);
        }

        if ($result['blocked']) {
            return response()->json([
                'error' => 'invalid_password',
                'message' => $result['error'] ?? 'IP-адрес временно заблокирован.',
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

    /**
     * Initial setup of cloud password (allowed only if not already set).
     */
    public function setup(Request $request): JsonResponse
    {
        $password = (string) $request->input('password', '');
        $passwordConfirmation = (string) $request->input('password_confirmation', '');
        $token = trim((string) (
            $request->bearerToken()
            ?: $request->input('token')
            ?: ($request->hasSession() ? $request->session()->get('tma_user_id') : '')
        ));

        if (empty($password)) {
            return response()->json([
                'error' => 'missing_password',
                'message' => 'Укажите облачный пароль.',
            ], 422);
        }

        if (! empty($passwordConfirmation) && $password !== $passwordConfirmation) {
            return response()->json([
                'error' => 'password_mismatch',
                'message' => 'Пароли не совпадают.',
            ], 422);
        }

        $user = $request->attributes->get('user') ?? auth()->user();
        if (! $user && ! empty($token)) {
            $user = $this->resolveUserFromToken($token);
        }

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // STRICT SECURITY CHECK: Never allow setup if user already has a cloud password!
        if ($user->hasCloudPassword()) {
            return response()->json([
                'error' => 'already_configured',
                'message' => 'Облачный пароль уже установлен. Для изменения используйте форму смены пароля.',
            ], 422);
        }

        $clientIp = $this->geoIpService->resolveClientIp($request);
        $result = $this->securityService->setupCloudPassword($user, $password, $token ?: null, $clientIp);

        if ($result['success']) {
            return response()->json([
                'status' => 'ok',
                'message' => 'Облачный пароль успешно установлен.',
            ]);
        }

        return response()->json([
            'error' => 'invalid_parameters',
            'message' => $result['error'] ?? 'Ошибка установки пароля.',
        ], 422);
    }

    /**
     * Change existing cloud password (requires valid current password).
     */
    public function change(Request $request): JsonResponse
    {
        $user = $request->attributes->get('user') ?? auth()->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $currentPassword = (string) $request->input('current_password', '');
        $newPassword = (string) $request->input('new_password', '');
        $newPasswordConfirmation = (string) $request->input('new_password_confirmation', '');

        if (empty($newPassword)) {
            return response()->json([
                'error' => 'missing_password',
                'message' => 'Укажите новый пароль.',
            ], 422);
        }

        if (! empty($newPasswordConfirmation) && $newPassword !== $newPasswordConfirmation) {
            return response()->json([
                'error' => 'password_mismatch',
                'message' => 'Новые пароли не совпадают.',
            ], 422);
        }

        $clientIp = $this->geoIpService->resolveClientIp($request);
        $result = $this->securityService->changeCloudPassword($user, $currentPassword, $newPassword, $clientIp);

        if ($result['success']) {
            return response()->json([
                'status' => 'ok',
                'message' => 'Облачный пароль успешно изменен.',
            ]);
        }

        return response()->json([
            'error' => 'invalid_password',
            'message' => $result['error'] ?? 'Ошибка изменения пароля.',
        ], 422);
    }

    /**
     * Reset cloud password by administrator (owner only).
     */
    public function reset(Request $request, User $user): JsonResponse
    {
        $actor = $request->attributes->get('user') ?? auth()->user();
        if (! $actor || ! $actor->isOwner()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $this->securityService->adminResetCloudPassword($user, $actor);

        return response()->json([
            'status' => 'ok',
            'message' => "Облачный пароль пользователя {$user->system_name} успешно сброшен.",
        ]);
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
