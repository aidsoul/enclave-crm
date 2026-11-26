<?php

use App\Enums\UserRole;
use App\Services\RoleService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user-{telegramUserId}', function ($user, $telegramUserId) {
    return (string) ($user->telegram_user_id ?? '') === (string) $telegramUserId;
});

Broadcast::channel('orders-live', function ($user) {
    return $user->role === UserRole::OWNER || $user->role === UserRole::MANAGER;
});

Broadcast::channel('order.{orderId}.courier', function ($user, $orderId) {
    return app(RoleService::class)->canAccessOrderChat($user, (int) $orderId);
});

Broadcast::channel('order.{orderId}.internal', function ($user, $orderId) {
    if ($user->isCourier()) {
        return false;
    }

    return app(RoleService::class)->canAccessOrderChat($user, (int) $orderId);
});
