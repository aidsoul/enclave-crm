<?php

namespace App\Http\Telegram\Handlers;

use App\Enums\SettingKey;
use App\Models\User;
use App\Services\Orders\OrderAssigner;
use App\Services\SettingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class OrderCallbacks
{
    public function __construct(
        protected OrderAssigner $orderAssigner,
        protected SettingService $settingService
    ) {}

    public function acceptManager(Nutgram $bot, ?int $orderId = null): void
    {
        $orderId = $this->resolveOrderId($bot, $orderId);
        if ($orderId <= 0) {
            return;
        }

        $user = $this->getUserFromUpdate($bot);
        if (! $user) {
            $bot->answerCallbackQuery(text: 'Ошибка: пользователь не найден');

            return;
        }

        if (! $user->isManager() && ! $user->isOwner()) {
            $bot->answerCallbackQuery(text: 'Ошибка: вы не менеджер');

            return;
        }

        try {
            $order = $this->orderAssigner->acceptOrder($orderId, $user->id, $user);
            $bot->answerCallbackQuery(text: 'Заказ принят!');

            $deleteOnAccept = (bool) $this->settingService->get(SettingKey::DELETE_CHAT_MESSAGES_ON_ACCEPT, null);
            if (! $deleteOnAccept) {
                $order->loadMissing(['site', 'customer']);
                $siteInfo = $order->site?->domain ?? '—';
                $phoneInfo = $order->customer?->formatted_phone ?? $order->customer?->phone ?? '—';
                $bot->sendMessage("Вы приняли заказ #{$orderId}.\nСайт: {$siteInfo}\nКлиент: {$order->customer?->name}\nТелефон: {$phoneInfo}\nEmail: ".($order->customer?->email ?? '—')."\nАдрес: ".($order->customer?->address ?? '—'), chat_id: $bot->chatId());
            }
        } catch (\InvalidArgumentException $e) {
            $bot->answerCallbackQuery(text: $e->getMessage());
        } catch (\Exception $e) {
            Log::channel('webhooks')->error('OrderCallbacks acceptManager error', [
                'order_id' => $orderId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            $bot->answerCallbackQuery(text: 'Ошибка при принятии заказа');
        }
    }

    public function acceptCourier(Nutgram $bot, ?int $orderId = null): void
    {
        $orderId = $this->resolveOrderId($bot, $orderId);
        if ($orderId <= 0) {
            return;
        }

        $user = $this->getUserFromUpdate($bot);
        if (! $user) {
            $bot->answerCallbackQuery(text: 'Ошибка: пользователь не найден');

            return;
        }

        if (! $user->isCourier()) {
            $bot->answerCallbackQuery(text: 'Ошибка: вы не курьер');

            return;
        }

        try {
            $this->orderAssigner->assignCourier($orderId, $user->id, $user->id, $user, allowReassign: false);
            $bot->answerCallbackQuery(text: 'Заказ принят!');
            $bot->sendMessage("Заказ #{$orderId} подтверждён. Ожидайте адрес доставки в Mini App.", chat_id: $bot->chatId());
        } catch (\InvalidArgumentException $e) {
            $bot->answerCallbackQuery(text: $e->getMessage());
        } catch (\Exception $e) {
            Log::channel('webhooks')->error('OrderCallbacks acceptCourier error', [
                'order_id' => $orderId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            $bot->answerCallbackQuery(text: 'Ошибка при принятии заказа');
        }
    }

    public function rejectCourier(Nutgram $bot, ?int $orderId = null): void
    {
        $orderId = $this->resolveOrderId($bot, $orderId);
        if ($orderId <= 0) {
            return;
        }

        $courier = $this->getUserFromUpdate($bot);
        if (! $courier || ! $courier->isCourier()) {
            $bot->answerCallbackQuery(text: 'Ошибка: вы не курьер');

            return;
        }

        try {
            $this->orderAssigner->rejectCourier($orderId, $courier->id, $courier);
            $bot->answerCallbackQuery(text: 'Заказ отклонён');
            $bot->sendMessage("Заказ #{$orderId} отклонён.", chat_id: $bot->chatId());
        } catch (\InvalidArgumentException $e) {
            $bot->answerCallbackQuery(text: $e->getMessage());
        } catch (\Exception $e) {
            Log::channel('webhooks')->error('OrderCallbacks rejectCourier error', [
                'order_id' => $orderId,
                'user_id' => $courier->id,
                'error' => $e->getMessage(),
            ]);
            $bot->answerCallbackQuery(text: 'Ошибка при отклонении заказа');
        }
    }

    public function accept(Nutgram $bot, ?int $orderId = null): void
    {
        $callbackData = $bot->callbackQuery()?->data ?? '';
        if (str_starts_with($callbackData, 'manager_order:')) {
            $this->acceptManager($bot, $orderId);
        } elseif (str_starts_with($callbackData, 'order:')) {
            $this->acceptCourier($bot, $orderId);
        }
    }

    public function reject(Nutgram $bot, ?int $orderId = null): void
    {
        $this->rejectCourier($bot, $orderId);
    }

    protected function resolveOrderId(Nutgram $bot, ?int $orderId): int
    {
        if ($orderId !== null && $orderId > 0) {
            return $orderId;
        }

        $parts = explode(':', $bot->callbackQuery()?->data ?? '');

        return (int) ($parts[1] ?? 0);
    }

    protected function getUserFromUpdate(Nutgram $bot): ?User
    {
        $from = $bot->callbackQuery()?->from;
        if (! $from) {
            return null;
        }

        $telegramId = (string) $from->id;

        $user = Cache::remember("tg_user:{$telegramId}", 60, function () use ($telegramId) {
            return User::where('telegram_user_id', $telegramId)
                ->active()
                ->first();
        });

        if ($user && ! $user->is_active) {
            Cache::forget("tg_user:{$telegramId}");

            return null;
        }

        return $user;
    }
}
