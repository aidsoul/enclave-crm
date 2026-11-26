<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class TelegramController extends Controller
{
    /**
     * Update types recognized in the Telegram Bot API payload.
     */
    private const UPDATE_TYPES = [
        'message',
        'edited_message',
        'channel_post',
        'edited_channel_post',
        'inline_query',
        'chosen_inline_result',
        'callback_query',
        'shipping_query',
        'pre_checkout_query',
        'poll',
        'poll_answer',
        'my_chat_member',
        'chat_member',
        'chat_join_request',
    ];

    public function webhook(Request $request, Nutgram $bot)
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

        $type = 'unknown';
        foreach (self::UPDATE_TYPES as $candidate) {
            if ($request->has($candidate)) {
                $type = $candidate;
                break;
            }
        }

        Log::channel('webhooks')->info('Telegram webhook received', [
            'update_type' => $type,
            'update_id' => $request->input('update_id'),
        ]);

        $bot->run();

        return response()->json(['ok' => true]);
    }
}
