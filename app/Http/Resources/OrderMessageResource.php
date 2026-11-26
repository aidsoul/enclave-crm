<?php

namespace App\Http\Resources;

use App\Models\OrderMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderMessage
 */
class OrderMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentUser = $request->attributes->get('user') ?? auth()->user();
        $sender = $this->user;
        $isCurrentUserCourier = $currentUser && $currentUser->isCourier();

        if ($isCurrentUserCourier && $sender && ! $sender->isCourier()) {
            $senderName = 'Менеджер';
            $senderRole = 'manager';
        } else {
            $senderName = $sender?->system_name ?: ($sender?->name ?: 'Пользователь');
            $senderRole = $sender?->role?->value ?? 'system';
        }

        $isMine = false;
        if ($currentUser && $this->user_id) {
            $isMine = (int) $this->user_id === (int) $currentUser->id;
        }

        $isOwner = $currentUser && $currentUser->isOwner();

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'channel' => $this->channel,
            'message' => $this->message,
            'is_mine' => $isMine,
            'sender' => [
                'name' => $senderName,
                'role' => $senderRole,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'edited_at' => $isOwner ? $this->edited_at?->toIso8601String() : null,
            'is_edited' => $isOwner && $this->edited_at !== null,
            'can_edit' => $currentUser ? $this->canBeEditedBy($currentUser) : false,
            'can_delete' => $currentUser ? $this->canBeDeletedBy($currentUser) : false,
            'formatted_time' => $this->created_at?->format('H:i'),
            'formatted_date' => $this->created_at?->format('d.m.Y'),
        ];
    }
}
