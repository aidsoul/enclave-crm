<?php

namespace App\Http\Telegram\Handlers;

use App\Models\User;
use App\Services\PanicService;
use SergiX44\Nutgram\Nutgram;

class PanicCallback
{
    public function __construct(
        protected PanicService $panicService
    ) {}

    public function __invoke(Nutgram $bot): void
    {
        $callbackData = $bot->callbackQuery()->data;

        if (! str_starts_with($callbackData, 'panic:') && ! str_starts_with($callbackData, 'panic_confirm:')) {
            return;
        }

        $parts = explode(':', $callbackData);
        $targetUserId = (int) ($parts[1] ?? 0);

        if ($targetUserId <= 0) {
            $bot->answerCallbackQuery(text: 'Ошибка: неверный ID пользователя');

            return;
        }

        $adminUser = $this->getAdminFromUpdate($bot);

        if (! $adminUser || ! $adminUser->isOwner()) {
            $bot->answerCallbackQuery(text: 'Недостаточно прав');

            return;
        }

        try {
            $this->panicService->triggerPanic($targetUserId, $adminUser->id);

            $bot->answerCallbackQuery(text: 'Пользователь заблокирован');
            $bot->sendMessage('Пользователь успешно заблокирован.');

        } catch (\Exception $e) {
            $bot->answerCallbackQuery(text: 'Ошибка при блокировке');
        }
    }

    protected function getAdminFromUpdate(Nutgram $bot): ?User
    {
        $from = $bot->callbackQuery()->from;

        if (! $from) {
            return null;
        }

        $telegramId = (string) $from->id;

        return User::where('telegram_user_id', $telegramId)
            ->active()
            ->first();
    }
}
