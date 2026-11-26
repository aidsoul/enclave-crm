<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderMessageDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public int $messageId,
        public int $orderId,
        public string $channel
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("order.{$this->orderId}.{$this->channel}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'OrderMessageDeleted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->messageId,
            'order_id' => $this->orderId,
            'channel' => $this->channel,
        ];
    }
}
