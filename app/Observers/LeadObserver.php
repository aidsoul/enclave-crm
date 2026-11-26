<?php

namespace App\Observers;

use App\Enums\ActionType;
use App\Enums\LeadStatus;
use App\Models\ActionLog;
use App\Models\Lead;
use App\Models\User;

class LeadObserver
{
    public function created(Lead $lead): void
    {
        $actor = $this->getActor();

        $details = "Заявка #{$lead->id}";
        if ($lead->type_label) {
            $details .= " ({$lead->type_label})";
        }
        if ($lead->name || $lead->phone) {
            $details .= ': '.trim(($lead->name ?: '').' '.($lead->formatted_phone ?: $lead->phone ?: ''));
        }

        ActionLog::create([
            'actor_id' => $actor?->id,
            'lead_id' => $lead->id,
            'site_id' => $lead->site_id,
            'action_type' => ActionType::CREATE,
            'field_name' => 'lead',
            'old_value' => null,
            'new_value' => $details,
            'created_at' => now(),
        ]);
    }

    public function updated(Lead $lead): void
    {
        $actor = $this->getActor();

        if ($lead->wasChanged('status')) {
            $oldStatus = $lead->getOriginal('status');
            $oldLabel = $oldStatus instanceof LeadStatus
                ? $oldStatus->label()
                : (LeadStatus::tryFrom((string) $oldStatus)?->label() ?? (string) $oldStatus);
            $newLabel = $lead->status instanceof LeadStatus
                ? $lead->status->label()
                : (LeadStatus::tryFrom((string) $lead->status)?->label() ?? (string) $lead->status);

            ActionLog::create([
                'actor_id' => $actor?->id,
                'lead_id' => $lead->id,
                'site_id' => $lead->site_id,
                'action_type' => ActionType::STATUS_CHANGE,
                'field_name' => 'status',
                'old_value' => $oldLabel,
                'new_value' => $newLabel,
                'created_at' => now(),
            ]);
        }

        if ($lead->wasChanged('manager_id')) {
            $oldManager = $lead->getOriginal('manager_id')
                ? User::find($lead->getOriginal('manager_id'))?->system_name
                : 'Не назначен';
            $newManager = $lead->manager_id
                ? User::find($lead->manager_id)?->system_name
                : 'Не назначен';

            ActionLog::create([
                'actor_id' => $actor?->id,
                'lead_id' => $lead->id,
                'site_id' => $lead->site_id,
                'action_type' => ActionType::ASSIGN,
                'field_name' => 'manager_id',
                'old_value' => $oldManager,
                'new_value' => $newManager,
                'created_at' => now(),
            ]);
        }

        if ($lead->wasChanged('converted_order_id') && $lead->converted_order_id) {
            ActionLog::create([
                'actor_id' => $actor?->id,
                'lead_id' => $lead->id,
                'site_id' => $lead->site_id,
                'order_id' => $lead->converted_order_id,
                'action_type' => ActionType::STATUS_CHANGE,
                'field_name' => 'converted_order_id',
                'old_value' => null,
                'new_value' => "Заказ #{$lead->converted_order_id}",
                'created_at' => now(),
            ]);
        }

        if ($lead->wasChanged('message')) {
            ActionLog::create([
                'actor_id' => $actor?->id,
                'lead_id' => $lead->id,
                'site_id' => $lead->site_id,
                'action_type' => ActionType::UPDATE,
                'field_name' => 'message',
                'old_value' => $lead->getOriginal('message'),
                'new_value' => $lead->message,
                'created_at' => now(),
            ]);
        }

        foreach (['name', 'phone', 'type'] as $field) {
            if ($lead->wasChanged($field)) {
                ActionLog::create([
                    'actor_id' => $actor?->id,
                    'lead_id' => $lead->id,
                    'site_id' => $lead->site_id,
                    'action_type' => ActionType::UPDATE,
                    'field_name' => "lead.{$field}",
                    'old_value' => (string) $lead->getOriginal($field),
                    'new_value' => (string) $lead->$field,
                    'created_at' => now(),
                ]);
            }
        }
    }

    public function deleting(Lead $lead): void
    {
        $actor = $this->getActor();

        $details = "Заявка #{$lead->id}";
        if ($lead->type_label) {
            $details .= " ({$lead->type_label})";
        }
        if ($lead->name || $lead->phone) {
            $details .= ': '.trim(($lead->name ?: '').' '.($lead->formatted_phone ?: $lead->phone ?: ''));
        }

        ActionLog::create([
            'actor_id' => $actor?->id,
            'lead_id' => $lead->id,
            'site_id' => $lead->site_id,
            'action_type' => ActionType::DELETE,
            'field_name' => 'lead',
            'old_value' => $details,
            'new_value' => null,
            'created_at' => now(),
        ]);
    }

    protected function getActor(): ?User
    {
        $user = request()?->attributes?->get('user') ?? auth()->user();

        return $user instanceof User ? $user : null;
    }
}
