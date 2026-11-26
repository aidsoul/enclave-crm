<?php

namespace App\Http\Telegram\Handlers;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class DesktopCommand
{
    public function __invoke(Nutgram $bot): void
    {
        $tgUser = $bot->user();

        if (! $tgUser) {
            return;
        }

        $user = User::where('telegram_user_id', (string) $tgUser->id)
            ->active()
            ->first();

        if (! $user) {
            $bot->sendMessage(
                text: 'Доступ заблокирован или аккаунт не найден. Обратитесь к владельцу CRM.',
                chat_id: $tgUser->id
            );

            return;
        }

        $token = Str::random(64);
        Redis::setex("desktop_auth:{$token}", 300, json_encode([
            'user_id' => $user->id,
            'created_at' => now()->timestamp,
        ]));

        $baseUrl = rtrim((string) config('nutgram.webapp_url', config('app.url')), '/');
        $url = "{$baseUrl}/tma/login/desktop?token={$token}";

        try {
            $bot->sendMessage(
                text: "🖥 <b>Вход в CRM на компьютере</b>\n\nСсылка действует 5 минут и работает 1 раз:\n<a href=\"{$url}\">{$url}</a>",
                chat_id: $tgUser->id,
                parse_mode: 'HTML',
                reply_markup: InlineKeyboardMarkup::make()->addRow(
                    InlineKeyboardButton::make(text: '💻 Открыть CRM на ПК', url: $url)
                )
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send desktop link via /desktop command', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
