<?php

namespace App\Events;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public Order $order,
        public string $courierTelegramUserId
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user-{$this->courierTelegramUserId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'OrderAssigned';
    }

    public function broadcastWith(): array
    {
        $this->order->loadMissing(['customer', 'site', 'manager', 'delivery.courier', 'payment', 'items', 'discounts']);

        return [
            'event' => 'OrderAssigned',
            'order_id' => $this->order->id,
            'courier_telegram_user_id' => (string) $this->courierTelegramUserId,
            'status' => $this->order->status->value,
            'order' => (new OrderResource($this->order))->resolve(),
        ];
    }
}
