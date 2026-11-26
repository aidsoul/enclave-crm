<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateTelegramWebhookSecret
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secretToken = config('nutgram.secret_token');
        $headerToken = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (
            ! is_string($secretToken) ||
            $secretToken === '' ||
            ! is_string($headerToken) ||
            $headerToken === '' ||
            ! hash_equals($secretToken, $headerToken)
        ) {
            Log::channel('webhooks')->warning('Telegram webhook rejected: invalid or missing secret token', [
                'ip' => $request->ip(),
                'has_configured_secret' => is_string($secretToken) && $secretToken !== '',
                'has_header_secret' => is_string($headerToken) && $headerToken !== '',
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}

