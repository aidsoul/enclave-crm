<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public Order $order) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('orders-live'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'OrderCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'order' => [
                'id' => $this->order->id,
                'site_id' => $this->order->site_id,
                'site' => $this->order->site ? [
                    'id' => $this->order->site->id,
                    'name' => $this->order->site->name,
                    'domain' => $this->order->site->domain,
                    'display_name' => $this->order->site->display_name,
                ] : null,
                'customer' => $this->order->customer ? [
                    'id' => $this->order->customer->id,
                    'name' => $this->order->customer->name,
                    'phone' => $this->order->customer->phone,
                    'email' => $this->order->customer->email,
                    'address' => $this->order->customer->address,
                ] : null,
                'total_price' => $this->order->total_price,
                'status' => $this->order->status->value,
                'manager_id' => $this->order->manager_id,
                'manager' => $this->order->manager ? [
                    'id' => $this->order->manager->id,
                    'system_name' => $this->order->manager->system_name,
                ] : null,
                'created_at' => $this->order->created_at->toIso8601String(),
            ],
        ];
    }
}
