<?php

namespace App\Observers;

use App\Enums\ActionType;
use App\Models\ActionLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Support\Facades\Cache;

class SettingObserver
{
    public function saved(Setting $setting): void
    {
        $this->invalidate($setting);
    }

    public function created(Setting $setting): void
    {
        $actor = $this->getActor();
        $targetUserId = $setting->scope === 'user' ? $setting->user_id : null;
        $fieldName = $setting->scope === 'user' ? "setting.user.{$setting->key}" : "setting.{$setting->key}";

        ActionLog::create([
            'actor_id' => $actor?->id,
            'target_user_id' => $targetUserId,
            'action_type' => ActionType::CREATE,
            'field_name' => $fieldName,
            'old_value' => null,
            'new_value' => $this->sanitizeValue($setting->key, $setting->value),
            'created_at' => now(),
        ]);
    }

    public function updated(Setting $setting): void
    {
        if ($setting->wasChanged('value')) {
            $actor = $this->getActor();
            $targetUserId = $setting->scope === 'user' ? $setting->user_id : null;
            $fieldName = $setting->scope === 'user' ? "setting.user.{$setting->key}" : "setting.{$setting->key}";

            ActionLog::create([
                'actor_id' => $actor?->id,
                'target_user_id' => $targetUserId,
                'action_type' => ActionType::UPDATE,
                'field_name' => $fieldName,
                'old_value' => $this->sanitizeValue($setting->key, $setting->getOriginal('value')),
                'new_value' => $this->sanitizeValue($setting->key, $setting->value),
                'created_at' => now(),
            ]);
        }
    }

    public function deleted(Setting $setting): void
    {
        $this->invalidate($setting);

        $actor = $this->getActor();
        $targetUserId = $setting->scope === 'user' ? $setting->user_id : null;
        $fieldName = $setting->scope === 'user' ? "setting.user.{$setting->key}" : "setting.{$setting->key}";

        ActionLog::create([
            'actor_id' => $actor?->id,
            'target_user_id' => $targetUserId,
            'action_type' => ActionType::DELETE,
            'field_name' => $fieldName,
            'old_value' => $this->sanitizeValue($setting->key, $setting->value),
            'new_value' => null,
            'created_at' => now(),
        ]);
    }

    protected function sanitizeValue(string $key, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $lower = strtolower($key);
        if (str_contains($lower, 'token') || str_contains($lower, 'secret') || str_contains($lower, 'password') || str_contains($lower, 'pin') || str_contains($lower, 'api_key')) {
            return '***';
        }

        return (string) $value;
    }

    protected function getActor(): ?User
    {
        $user = request()?->attributes?->get('user') ?? auth()->user();

        return $user instanceof User ? $user : null;
    }

    protected function invalidate(Setting $setting): void
    {
        $keys = array_unique(array_filter([$setting->key, $setting->getOriginal('key')]));

        foreach ($keys as $key) {
            Cache::forget("setting:global:{$key}");
            Cache::forget("setting:raw:{$key}:global:null");

            $roles = array_unique(array_filter([$setting->role, $setting->getOriginal('role')]));
            foreach ($roles as $role) {
                Cache::forget("setting:role:{$role}:{$key}");
                Cache::forget("setting:raw:{$key}:role:{$role}");
            }

            $userIds = array_unique(array_filter([$setting->user_id, $setting->getOriginal('user_id')]));
            foreach ($userIds as $userId) {
                Cache::forget("setting:user:{$userId}:{$key}");
                Cache::forget("setting:raw:{$key}:user:{$userId}");
            }
        }

        Cache::forget('settings:all_system');
        app(SettingService::class)->clearRuntimeCache();
    }
}
