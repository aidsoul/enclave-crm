<?php

namespace App\Observers;

use App\Enums\ActionType;
use App\Models\ActionLog;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class SiteObserver
{
    public function created(Site $site): void
    {
        $actor = $this->getActor();

        ActionLog::create([
            'actor_id' => $actor?->id,
            'site_id' => $site->id,
            'action_type' => ActionType::CREATE,
            'field_name' => 'site',
            'old_value' => null,
            'new_value' => $site->name,
            'created_at' => now(),
        ]);
    }

    public function updated(Site $site): void
    {
        $actor = $this->getActor();

        if ($site->wasChanged('api_token_hash') || $site->wasChanged('api_token')) {
            ActionLog::create([
                'actor_id' => $actor?->id,
                'site_id' => $site->id,
                'action_type' => ActionType::UPDATE,
                'field_name' => 'api_token',
                'old_value' => '***',
                'new_value' => $site->api_token_hash ? '*** (ротирован)' : 'отозван',
                'created_at' => now(),
            ]);
        }

        if ($site->wasChanged('is_active')) {
            ActionLog::create([
                'actor_id' => $actor?->id,
                'site_id' => $site->id,
                'action_type' => ActionType::STATUS_CHANGE,
                'field_name' => 'is_active',
                'old_value' => $site->getOriginal('is_active') ? 'Активен' : 'Отключён',
                'new_value' => $site->is_active ? 'Активен' : 'Отключён',
                'created_at' => now(),
            ]);
        }

        foreach (['name', 'domain', 'default_purchase_percent'] as $field) {
            if ($site->wasChanged($field)) {
                ActionLog::create([
                    'actor_id' => $actor?->id,
                    'site_id' => $site->id,
                    'action_type' => ActionType::UPDATE,
                    'field_name' => "site.{$field}",
                    'old_value' => (string) $site->getOriginal($field),
                    'new_value' => (string) $site->$field,
                    'created_at' => now(),
                ]);
            }
        }
    }

    public function saved(Site $site): void
    {
        $this->invalidate($site);
    }

    public function deleting(Site $site): void
    {
        $actor = $this->getActor();

        ActionLog::create([
            'actor_id' => $actor?->id,
            'site_id' => $site->id,
            'action_type' => ActionType::DELETE,
            'field_name' => 'site',
            'old_value' => $site->name,
            'new_value' => null,
            'created_at' => now(),
        ]);
    }

    public function deleted(Site $site): void
    {
        $this->invalidate($site);
    }

    protected function invalidate(Site $site): void
    {
        Cache::forget('sites:reference_list');
        Cache::forget("site:{$site->id}");

        $hashes = array_unique(array_filter([$site->api_token_hash, $site->getOriginal('api_token_hash')]));
        foreach ($hashes as $hash) {
            Cache::forget("site:token_hash:{$hash}");
        }
    }

    protected function getActor(): ?User
    {
        $user = request()?->attributes?->get('user') ?? auth()->user();

        return $user instanceof User ? $user : null;
    }
}
