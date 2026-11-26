<?php

namespace App\Observers;

use App\Enums\ActionType;
use App\Models\ActionLog;
use App\Models\Customer;
use App\Models\User;

class CustomerObserver
{
    public function created(Customer $customer): void
    {
        $actor = $this->getActor();

        ActionLog::create([
            'actor_id' => $actor?->id,
            'customer_id' => $customer->id,
            'site_id' => $customer->site_id,
            'action_type' => ActionType::CREATE,
            'field_name' => 'customer',
            'old_value' => null,
            'new_value' => trim(($customer->name ?: 'Клиент #'.$customer->id).' '.($customer->phone ?: '')),
            'created_at' => now(),
        ]);
    }

    public function updated(Customer $customer): void
    {
        $actor = $this->getActor();

        if ($customer->wasChanged('is_blacklisted')) {
            ActionLog::create([
                'actor_id' => $actor?->id,
                'customer_id' => $customer->id,
                'site_id' => $customer->site_id,
                'action_type' => ActionType::STATUS_CHANGE,
                'field_name' => 'is_blacklisted',
                'old_value' => $customer->getOriginal('is_blacklisted') ? 'В чёрном списке' : 'Не в ЧС',
                'new_value' => $customer->is_blacklisted ? 'В чёрном списке' : 'Не в ЧС',
                'created_at' => now(),
            ]);
        }

        if ($customer->wasChanged('blacklist_reason')) {
            ActionLog::create([
                'actor_id' => $actor?->id,
                'customer_id' => $customer->id,
                'site_id' => $customer->site_id,
                'action_type' => ActionType::UPDATE,
                'field_name' => 'blacklist_reason',
                'old_value' => (string) $customer->getOriginal('blacklist_reason'),
                'new_value' => (string) $customer->blacklist_reason,
                'created_at' => now(),
            ]);
        }

        $otherFields = ['name', 'phone', 'email', 'address', 'tags'];
        foreach ($otherFields as $field) {
            if ($customer->wasChanged($field)) {
                $old = $customer->getOriginal($field);
                $new = $customer->$field;
                ActionLog::create([
                    'actor_id' => $actor?->id,
                    'customer_id' => $customer->id,
                    'site_id' => $customer->site_id,
                    'action_type' => ActionType::UPDATE,
                    'field_name' => "customer.{$field}",
                    'old_value' => is_array($old) ? json_encode($old, JSON_UNESCAPED_UNICODE) : (string) $old,
                    'new_value' => is_array($new) ? json_encode($new, JSON_UNESCAPED_UNICODE) : (string) $new,
                    'created_at' => now(),
                ]);
            }
        }
    }

    public function deleting(Customer $customer): void
    {
        $actor = $this->getActor();

        ActionLog::create([
            'actor_id' => $actor?->id,
            'customer_id' => $customer->id,
            'site_id' => $customer->site_id,
            'action_type' => ActionType::DELETE,
            'field_name' => 'customer',
            'old_value' => trim(($customer->name ?: 'Клиент #'.$customer->id).' '.($customer->phone ?: '')),
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
