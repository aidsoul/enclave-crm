<?php

namespace App\Policies;

use App\Enums\SettingKey;
use App\Enums\SettingScope;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\SettingAccessService;

class SettingsPolicy
{
    public function __construct(protected SettingAccessService $settings) {}

    public function view(User $user, SettingKey $key): bool
    {
        return $this->settings->canView($user, $key);
    }

    public function edit(
        User $user,
        SettingKey $key,
        SettingScope $scope,
        ?UserRole $role = null,
        ?int $targetUserId = null
    ): bool {
        return $this->settings->canEdit($user, $key, $scope, $role, $targetUserId);
    }
}
