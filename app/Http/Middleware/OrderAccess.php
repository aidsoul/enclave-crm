<?php

namespace App\Http\Middleware;

use App\Services\RoleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderAccess
{
    public function __construct(protected RoleService $roleService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('user');

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $orderId = $request->route('id') ?? $request->route('order');

        if (! $orderId || ! $this->roleService->canAccessOrder($user, (int) $orderId)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
