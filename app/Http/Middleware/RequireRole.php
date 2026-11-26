<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->attributes->get('user');

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $allowed = array_map('strtolower', $roles);

        if (! in_array($user->role->value, $allowed, true)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
