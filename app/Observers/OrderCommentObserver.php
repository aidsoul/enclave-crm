<?php

namespace App\Observers;

use App\Enums\ActionType;
use App\Models\ActionLog;
use App\Models\OrderComment;

class OrderCommentObserver
{
    public function created(OrderComment $comment): void
    {
        $actorId = $comment->user_id ?? auth()->id() ?? request()?->attributes?->get('user')?->id;
        if ($actorId) {
            ActionLog::create([
                'actor_id' => $actorId,
                'order_id' => $comment->order_id,
                'action_type' => ActionType::CREATE,
                'field_name' => 'comment',
                'old_value' => null,
                'new_value' => $comment->body,
                'created_at' => now(),
            ]);
        }
    }

    public function updated(OrderComment $comment): void
    {
        $actorId = $comment->user_id ?? auth()->id() ?? request()?->attributes?->get('user')?->id;
        if ($actorId) {
            ActionLog::create([
                'actor_id' => $actorId,
                'order_id' => $comment->order_id,
                'action_type' => ActionType::UPDATE,
                'field_name' => 'comment',
                'old_value' => $comment->getOriginal('body'),
                'new_value' => $comment->body,
                'created_at' => now(),
            ]);
        }
    }

    public function deleted(OrderComment $comment): void
    {
        $actorId = $comment->user_id ?? auth()->id() ?? request()?->attributes?->get('user')?->id;
        if ($actorId) {
            ActionLog::create([
                'actor_id' => $actorId,
                'order_id' => $comment->order_id,
                'action_type' => ActionType::DELETE,
                'field_name' => 'comment',
                'old_value' => $comment->body,
                'new_value' => null,
                'created_at' => now(),
            ]);
        }
    }
}
