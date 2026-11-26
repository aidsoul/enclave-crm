<?php

namespace App\Http\Middleware;

use App\Models\Order;
use App\Services\RoleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderEdit
{
    public function __construct(protected RoleService $roleService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('user');

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $orderId = $request->route('id') ?? $request->route('order');
        $order = Order::findOrFail((int) $orderId);

        if (! $this->roleService->canEditOrder($user, $order)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $request->attributes->set('order', $order);

        return $next($request);
    }
}
