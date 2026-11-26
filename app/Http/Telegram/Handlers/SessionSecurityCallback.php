<?php

namespace App\Http\Telegram\Handlers;

use App\Models\User;
use App\Services\Security\SessionSecurityService;
use SergiX44\Nutgram\Nutgram;

class SessionSecurityCallback
{
    public function __construct(
        protected SessionSecurityService $securityService
    ) {}

    public function approve(Nutgram $bot, ?string $hash = null, ?string $code = null): void
    {
        if (! $hash || ! $code) {
            $parts = explode(':', $bot->callbackQuery()?->data ?? '');
            $hash = $hash ?: ($parts[1] ?? null);
            $code = $code ?: ($parts[2] ?? null);
        }

        if (! $hash || ! $code) {
            $bot->answerCallbackQuery(text: 'Ошибка: неверные параметры');

            return;
        }

        $user = $this->getUserFromUpdate($bot);
        if (! $user) {
            $bot->answerCallbackQuery(text: 'Ошибка: пользователь не найден');

            return;
        }

        $result = $this->securityService->approveByHash($hash, $code, $user);

        if ($result['success']) {
            $bot->answerCallbackQuery(text: '✅ Вход успешно подтвержден!');

            $ip = htmlspecialchars($result['ip'] ?? '');
            $country = htmlspecialchars($result['country'] ?? '');

            try {
                $bot->editMessageText(
                    "✅ <b>Вход подтвержден</b>\n\n".
                    "🌐 IP: <code>{$ip}</code>\n".
                    "🏳️ Страна: <b>{$country}</b>\n\n".
                    '<i>Доступ с этого устройства разрешен.</i>',
                    parse_mode: 'HTML'
                );
            } catch (\Throwable) {
            }
        } else {
            $bot->answerCallbackQuery(text: $result['error'] ?? 'Ошибка подтверждения');
        }
    }

    public function block(Nutgram $bot, ?string $hash = null): void
    {
        if (! $hash) {
            $parts = explode(':', $bot->callbackQuery()?->data ?? '');
            $hash = $parts[1] ?? null;
        }

        if (! $hash) {
            $bot->answerCallbackQuery(text: 'Ошибка: неверный ID сессии');

            return;
        }

        $user = $this->getUserFromUpdate($bot);
        if (! $user) {
            $bot->answerCallbackQuery(text: 'Ошибка: пользователь не найден');

            return;
        }

        $result = $this->securityService->blockByHash($hash, $user);

        if ($result['success']) {
            $bot->answerCallbackQuery(text: '🚨 Сессия аннулирована, IP заблокирован!');

            $ip = htmlspecialchars($result['ip'] ?? '');

            try {
                $bot->editMessageText(
                    "🚨 <b>Доступ заблокирован!</b>\n\n".
                    "Сессия была немедленно аннулирована.\n".
                    "IP-адрес <code>{$ip}</code> заблокирован.\n\n".
                    '<i>Злоумышленник отключен от системы.</i>',
                    parse_mode: 'HTML'
                );
            } catch (\Throwable) {
            }
        } else {
            $bot->answerCallbackQuery(text: $result['error'] ?? 'Ошибка блокировки');
        }
    }

    protected function getUserFromUpdate(Nutgram $bot): ?User
    {
        $from = $bot->callbackQuery()?->from;
        if (! $from) {
            return null;
        }

        $telegramId = (string) $from->id;

        return User::where('telegram_user_id', $telegramId)
            ->active()
            ->first();
    }
}
