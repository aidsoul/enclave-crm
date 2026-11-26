<?php

namespace App\Http\Middleware;

use App\Services\RoleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderStatusChange
{
    public function __construct(protected RoleService $roleService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('user');

        if (! $user || ! $this->roleService->canChangeOrderStatus($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
