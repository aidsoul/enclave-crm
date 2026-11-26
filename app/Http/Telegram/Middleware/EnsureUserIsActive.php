<?php

namespace App\Http\Telegram\Middleware;

use App\Models\User;
use SergiX44\Nutgram\Nutgram;

class EnsureUserIsActive
{
    public function __invoke(Nutgram $bot, $next): void
    {
        $from = $bot->user();

        if (! $from) {
            return;
        }

        $telegramId = (string) $from->id;

        $user = User::where('telegram_user_id', $telegramId)->first();

        if (! $user || ! $user->is_active) {
            $bot->sendMessage('Доступ заблокирован. Обратитесь к владельцу.');

            return;
        }

        $next($bot);
    }
}
