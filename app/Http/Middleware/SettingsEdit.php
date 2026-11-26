<?php

namespace App\Http\Middleware;

use App\Enums\SettingKey;
use App\Enums\SettingScope;
use App\Enums\UserRole;
use App\Services\SettingAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SettingsEdit
{
    public function __construct(protected SettingAccessService $settingAccess) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('user');

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $settingKey = SettingKey::tryFrom($request->route('key'));

        $scope = SettingScope::tryFrom((string) $request->input('scope', 'user'));
        if (! $scope) {
            return response()->json(['error' => 'Invalid scope'], 422);
        }
        $role = $request->filled('role') ? UserRole::tryFrom($request->input('role')) : null;
        $targetUserId = $request->input('user_id');

        if (! $settingKey || ! $this->settingAccess->canEdit($user, $settingKey, $scope, $role, $targetUserId)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
