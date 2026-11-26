<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\SettingKey;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Services\SettingService;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Cache;

class StaleOrderReminderService
{
    public function __construct(
        protected TelegramService $telegramService,
        protected SettingService $settingService
    ) {}

    public function checkAndRemind(?int $thresholdMinutes = null): int
    {
        $minutes = $thresholdMinutes ?? (int) ($this->settingService->get(SettingKey::STALE_ORDERS_THRESHOLD_MINUTES, null) ?? 120);
        $minutes = max(1, $minutes);

        $cutoff = now()->subMinutes($minutes);

        $staleOrders = Order::with(['site', 'customer', 'delivery.courier', 'manager'])
            ->whereNotIn('status', [OrderStatus::COMPLETED->value, OrderStatus::CANCELLED->value])
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('updated_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit(200)
            ->get();

        $sentCount = 0;
        $this->cachedManagersAndOwners = null;

        foreach ($staleOrders as $order) {
            $durationMinutes = (int) $order->updated_at->diffInMinutes(now());
            $hours = floor($durationMinutes / 60);
            $remMinutes = $durationMinutes % 60;
            $durationText = $hours > 0 ? "{$hours} ч.".($remMinutes > 0 ? " {$remMinutes} мин." : '') : "{$durationMinutes} мин.";

            $recipients = $this->resolveRecipients($order);

            foreach ($recipients as $recipient) {
                if ($this->shouldRemindUser($recipient, $order)) {
                    $this->telegramService->notifyStaleOrder($order, $recipient, $durationText);
                    $this->markReminded($recipient, $order);
                    $sentCount++;
                }
            }
        }

        $this->cachedManagersAndOwners = null;

        return $sentCount;
    }

    /** @var array<User>|null */
    protected ?array $cachedManagersAndOwners = null;

    /**
     * @return array<User>
     */
    protected function resolveRecipients(Order $order): array
    {
        $recipients = [];

        if (in_array($order->status, [OrderStatus::ASSIGNED, OrderStatus::AT_WAREHOUSE])) {
            $courier = $order->delivery->first()?->courier;
            if ($courier) {
                $recipients[$courier->id] = $courier;
            }
            if ($order->manager) {
                $recipients[$order->manager->id] = $order->manager;
            }
        } elseif (in_array($order->status, [OrderStatus::CREATED, OrderStatus::ACCEPTED])) {
            if ($order->manager) {
                $recipients[$order->manager->id] = $order->manager;
            } else {
                if ($this->cachedManagersAndOwners === null) {
                    $this->cachedManagersAndOwners = User::whereIn('role', [UserRole::OWNER, UserRole::MANAGER])
                        ->active()
                        ->get()
                        ->all();
                }
                foreach ($this->cachedManagersAndOwners as $user) {
                    $recipients[$user->id] = $user;
                }
            }
        }

        return array_values($recipients);
    }

    protected function shouldRemindUser(User $user, Order $order): bool
    {
        if ($user->telegram_user_id === null) {
            return false;
        }

        $chatEnabled = (bool) $this->settingService->get(SettingKey::NOTIFY_CHAT_ORDERS, $user);
        if (! $chatEnabled) {
            return false;
        }

        $enabled = (bool) $this->settingService->get(SettingKey::NOTIFY_STALE_ORDERS, $user);
        if (! $enabled) {
            return false;
        }

        $cacheKey = "stale_reminder:{$order->id}:{$order->status->value}:{$user->id}";

        return ! Cache::has($cacheKey);
    }

    protected function markReminded(User $user, Order $order): void
    {
        $cacheKey = "stale_reminder:{$order->id}:{$order->status->value}:{$user->id}";
        Cache::put($cacheKey, true, now()->addHours(4));
    }
}
