<?php

namespace App\Events;

use App\Models\OrderMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderMessageUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public OrderMessage $message) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("order.{$this->message->order_id}.{$this->message->channel}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'OrderMessageUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'order_id' => $this->message->order_id,
            'channel' => $this->message->channel,
            'message' => $this->message->message,
            'is_edited' => true,
            'edited_at' => $this->message->edited_at?->toIso8601String(),
        ];
    }
}
