<?php

namespace App\Http\Resources;

use App\Models\ActionLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ActionLog
 */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actor_id' => $this->actor_id,
            'actor' => $this->actor ? [
                'id' => $this->actor->id,
                'system_name' => $this->actor->system_name,
            ] : null,
            'order_id' => $this->order_id,
            'order' => $this->order ? [
                'id' => $this->order->id,
                'site' => $this->order->site ? [
                    'id' => $this->order->site->id,
                    'name' => $this->order->site->name,
                    'domain' => $this->order->site->domain,
                    'display_name' => $this->order->site->display_name,
                ] : null,
            ] : null,
            'lead_id' => $this->lead_id,
            'lead' => $this->lead ? [
                'id' => $this->lead->id,
                'name' => $this->lead->name,
                'phone' => $this->lead->phone,
                'formatted_phone' => $this->lead->formatted_phone,
                'phone_with_plus' => $this->lead->phone_with_plus,
                'type_label' => $this->lead->type_label,
                'site' => $this->lead->site ? [
                    'id' => $this->lead->site->id,
                    'name' => $this->lead->site->name,
                    'domain' => $this->lead->site->domain,
                ] : null,
            ] : null,
            'target_user_id' => $this->target_user_id,
            'target_user' => $this->targetUser ? [
                'id' => $this->targetUser->id,
                'system_name' => $this->targetUser->system_name,
                'role' => $this->targetUser->role?->value,
            ] : null,
            'customer_id' => $this->customer_id,
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
                'formatted_phone' => $this->customer->formatted_phone,
                'phone_with_plus' => $this->customer->phone_with_plus,
            ] : null,
            'site_id' => $this->site_id,
            'site' => $this->site ? [
                'id' => $this->site->id,
                'name' => $this->site->name,
                'domain' => $this->site->domain,
                'display_name' => $this->site->display_name,
            ] : null,
            'action_type' => $this->action_type,
            'field_name' => $this->field_name,
            'old_value' => $this->old_value,
            'new_value' => $this->new_value,
            'old_value_display' => $this->old_value_display ?? null,
            'new_value_display' => $this->new_value_display ?? null,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at,
        ];
    }
}
