<?php

namespace App\Events;

use App\Models\OrderMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public OrderMessage $message) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("order.{$this->message->order_id}.{$this->message->channel}"),
        ];

        $order = $this->message->order;
        if (! $order) {
            return $channels;
        }

        if ($this->message->channel === 'courier') {
            $sender = $this->message->user;
            $isSenderCourier = $sender && $sender->isCourier();

            if ($isSenderCourier) {
                $channels[] = new PrivateChannel('orders-live');
                if ($order->manager && $order->manager->telegram_user_id) {
                    $channels[] = new PrivateChannel("user-{$order->manager->telegram_user_id}");
                }
            } else {
                $courier = $order->courier;
                if (! $courier) {
                    $delivery = $order->delivery()->first();
                    $courier = $delivery?->courier;
                }
                if ($courier && $courier->telegram_user_id) {
                    $channels[] = new PrivateChannel("user-{$courier->telegram_user_id}");
                }
            }
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'OrderMessageSent';
    }

    public function broadcastWith(): array
    {
        $sender = $this->message->user;
        $senderRole = $sender?->role?->value ?? 'system';

        if ($this->message->channel === 'courier' && $sender && ! $sender->isCourier()) {
            $senderName = 'Менеджер';
        } else {
            $senderName = $sender?->system_name ?: ($sender?->name ?: 'Пользователь');
        }

        return [
            'id' => $this->message->id,
            'order_id' => $this->message->order_id,
            'channel' => $this->message->channel,
            'message' => $this->message->message,
            'sender_id' => $sender?->id,
            'sender_name' => $senderName,
            'sender_role' => $senderRole,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
