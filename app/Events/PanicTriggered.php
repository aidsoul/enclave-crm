<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PanicTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public string|int $userMapId) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user-{$this->userMapId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'Panic';
    }

    public function broadcastWith(): array
    {
        return [
            'event' => 'Panic',
            'user_map_id' => (string) $this->userMapId,
            'message' => 'Доступ заблокирован',
        ];
    }
}
