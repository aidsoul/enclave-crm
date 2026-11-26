<?php

namespace App\Services;

use App\Enums\SettingKey;
use App\Models\Setting;
use App\Models\User;

class UserSettingsInitializer
{
    public function __construct(
        protected SettingService $settingService
    ) {}

    public function initialize(User $user): void
    {
        foreach (SettingKey::cases() as $key) {
            if (! $key->isUserScoped()) {
                continue;
            }

            if (! in_array($user->role, $key->roles(), true)) {
                continue;
            }

            $exists = Setting::where('key', $key->value)
                ->where('scope', 'user')
                ->where('user_id', $user->id)
                ->exists();

            if (! $exists) {
                $this->settingService->set($key, $key->default(), 'user', (string) $user->id);
            }
        }
    }
}
