<?php

namespace App\Http\Resources;

use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invitation
 */
class InvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'token' => $this->token,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'system_name' => $this->system_name,
            'max_activations' => $this->max_activations,
            'used_count' => $this->used_count,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_active' => $this->is_active,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }
}
