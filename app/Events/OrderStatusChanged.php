<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public Order $order) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('orders-live'),
        ];

        if ($this->order->manager_id) {
            $manager = $this->order->manager;
            if ($manager && $manager->telegram_user_id) {
                $channels[] = new PrivateChannel("user-{$manager->telegram_user_id}");
            }
        }

        $delivery = $this->order->delivery()->first();
        if ($delivery && $delivery->courier && $delivery->courier->telegram_user_id) {
            $channels[] = new PrivateChannel("user-{$delivery->courier->telegram_user_id}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'OrderStatusChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'status' => $this->order->status->value,
            'is_edited' => $this->order->is_edited,
        ];
    }
}
