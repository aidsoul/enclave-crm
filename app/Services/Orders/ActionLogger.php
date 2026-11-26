<?php

namespace App\Services\Orders;

use App\Enums\ActionType;
use App\Enums\OrderStatus;
use App\Models\ActionLog;
use App\Models\Order;
use App\Models\User;

class ActionLogger
{
    protected function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
    }

    public function logCreate(Order $order, ?int $actorId): void
    {
        $actor = $actorId ? User::find($actorId) : null;

        ActionLog::create([
            'actor_id' => $actor?->id,
            'order_id' => $order->id,
            'site_id' => $order->site_id,
            'action_type' => ActionType::CREATE,
            'field_name' => null,
            'old_value' => null,
            'new_value' => null,
            'created_at' => now(),
        ]);
    }

    public function logUpdate(Order $order, string $field, mixed $old, mixed $new, ?User $actor = null): void
    {
        ActionLog::create([
            'actor_id' => $actor?->id,
            'order_id' => $order->id,
            'site_id' => $order->site_id,
            'action_type' => ActionType::UPDATE,
            'field_name' => $field,
            'old_value' => $this->stringify($old),
            'new_value' => $this->stringify($new),
            'created_at' => now(),
        ]);
    }

    public function logAssign(Order $order, User $courier, int $actorId): void
    {
        $delivery = $order->delivery()->first();

        ActionLog::create([
            'actor_id' => $actorId,
            'order_id' => $order->id,
            'site_id' => $order->site_id,
            'action_type' => ActionType::ASSIGN,
            'field_name' => 'delivery.user_id',
            'old_value' => (string) ($delivery?->user_id ?? ''),
            'new_value' => (string) $courier->id,
            'created_at' => now(),
        ]);
    }

    public function logStatusChange(Order $order, OrderStatus $old, OrderStatus $new, int $actorId): void
    {
        ActionLog::create([
            'actor_id' => $actorId,
            'order_id' => $order->id,
            'site_id' => $order->site_id,
            'action_type' => ActionType::STATUS_CHANGE,
            'field_name' => 'status',
            'old_value' => $old->value,
            'new_value' => $new->value,
            'created_at' => now(),
        ]);
    }
}
