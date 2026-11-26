<?php

namespace App\Http\Middleware;

use App\Enums\SettingKey;
use App\Services\SettingAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SettingsView
{
    public function __construct(protected SettingAccessService $settingAccess) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('user');

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $settingKey = SettingKey::tryFrom($request->route('key'));

        if (! $settingKey || ! $this->settingAccess->canView($user, $settingKey)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
