<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRegisteredViaInvitation implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public User $newUser, public ?User $inviter) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('orders-live'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'UserRegisteredViaInvitation';
    }

    public function broadcastWith(): array
    {
        return [
            'new_user' => [
                'id' => $this->newUser->id,
                'system_name' => $this->newUser->system_name,
                'role' => $this->newUser->role->value,
                'role_label' => $this->newUser->role->label(),
                'telegram_user_id' => $this->newUser->telegram_user_id,
                'created_at' => $this->newUser->created_at->toIso8601String(),
            ],
            'inviter' => $this->inviter ? [
                'id' => $this->inviter->id,
                'system_name' => $this->inviter->system_name,
                'role' => $this->inviter->role->value,
                'role_label' => $this->inviter->role->label(),
            ] : null,
        ];
    }
}
