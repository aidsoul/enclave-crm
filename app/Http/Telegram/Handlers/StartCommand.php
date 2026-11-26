<?php

namespace App\Http\Telegram\Handlers;

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\WebApp\WebAppInfo;

class StartCommand
{
    public function __invoke(Nutgram $bot, ?string $token = null): void
    {
        $user = $bot->user();

        if (! $user) {
            $this->safeSend($bot, 'Ошибка авторизации. Попробуйте через Mini App.');

            return;
        }

        $telegramId = (string) $user->id;

        $startParam = $token;
        if (! $startParam && $bot->message()) {
            $text = $bot->message()->text ?? '';
            if (str_starts_with($text, '/start ')) {
                $startParam = trim(substr($text, 7));
            }
        }

        $allowedUser = User::where('telegram_user_id', $telegramId)
            ->active()
            ->first();

        if (! $allowedUser && ! $startParam) {
            $this->safeSend($bot, 'Доступ заблокирован. Обратитесь к владельцу.');

            return;
        }

        if ($allowedUser) {
            if ($user->username && $allowedUser->telegram_username !== $user->username) {
                $allowedUser->update(['telegram_username' => $user->username]);
            }

            $this->safeSend($bot,
                "Привет, {$allowedUser->system_name}!\n\n".
                'Используйте кнопку ниже для открытия Mini App:',
                [
                    'reply_markup' => $this->buildMainKeyboard($allowedUser),
                ]
            );
        } else {
            $this->safeSend($bot,
                "Добро пожаловать!\n\n".
                'Используйте кнопку ниже для регистрации:',
                [
                    'reply_markup' => $this->buildInviteKeyboard($startParam),
                ]
            );
        }
    }

    protected function safeSend(Nutgram $bot, string $text, array $options = []): void
    {
        try {
            $bot->sendMessage($text, ...$options);
        } catch (\Throwable $e) {
            Log::channel('webhooks')->error('Telegram sendMessage failed', [
                'error' => $e->getMessage(),
                'text' => $text,
            ]);
        }
    }

    protected function buildInviteKeyboard(string $token): InlineKeyboardMarkup
    {
        $appUrl = rtrim((string) ($this->webAppUrl() ?? config('app.url')), '/');
        $invitation = Invitation::where('token', $token)->where('is_active', true)->first();
        $path = match ($invitation?->role) {
            UserRole::MANAGER => '/tma/manager',
            UserRole::COURIER => '/tma/courier',
            default => '',
        };
        $url = $appUrl.$path.'?invite='.urlencode($token);

        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '📦 Открыть приложение',
                    web_app: WebAppInfo::make($url)
                )
            );
    }

    protected function buildMainKeyboard(User $user): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '📦 Mini App',
                    web_app: WebAppInfo::make($this->buildMiniAppUrl($user))
                )
            );
    }

    protected function buildMiniAppUrl(User $user): string
    {
        $baseUrl = rtrim((string) ($this->webAppUrl() ?? config('app.url')), '/');

        return match ($user->role) {
            UserRole::MANAGER => $baseUrl.'/tma/manager',
            UserRole::COURIER => $baseUrl.'/tma/courier',
            default => $baseUrl,
        };
    }

    protected function webAppUrl(): ?string
    {
        return config('nutgram.webapp_url', config('app.url'));
    }
}
