<?php

namespace App\Services;

use App\Dto\AvailableSettingScope;
use App\Enums\SettingKey;
use App\Enums\SettingScope;
use App\Enums\UserRole;
use App\Models\User;

class SettingAccessService
{
    public function availableScopesFor(User $user, SettingKey $key): array
    {
        $scopes = [];

        if ($this->canEditGlobal($user, $key)) {
            $scopes[] = AvailableSettingScope::global();
        }

        if ($this->canEditRole($user, $key, UserRole::MANAGER)) {
            $scopes[] = AvailableSettingScope::forRole(
                UserRole::MANAGER->value,
                UserRole::MANAGER->label()
            );
        }

        if ($this->canEditRole($user, $key, UserRole::COURIER)) {
            $scopes[] = AvailableSettingScope::forRole(
                UserRole::COURIER->value,
                UserRole::COURIER->label()
            );
        }

        if ($this->canEditOwnUser($user, $key)) {
            $scopes[] = AvailableSettingScope::forUser();
        }

        return $scopes;
    }

    public function canView(User $user, SettingKey $key): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        return in_array($user->role, $key->roles());
    }

    public function canEdit(
        User $user,
        SettingKey $key,
        SettingScope $scope,
        ?UserRole $role = null,
        ?int $targetUserId = null
    ): bool {
        if ($user->isOwner()) {
            return true;
        }

        if ($scope === SettingScope::GLOBAL) {
            return false;
        }

        if ($scope === SettingScope::ROLE) {
            return false;
        }

        if ($scope === SettingScope::USER) {
            if (! $key->isUserScoped()) {
                return false;
            }

            $effectiveUserId = $targetUserId ?? $user->id;

            if ($user->id !== $effectiveUserId) {
                return false;
            }

            $allowed = $key->roles();

            if ($user->isManager()) {
                return in_array(UserRole::MANAGER, $allowed)
                    || in_array(UserRole::COURIER, $allowed);
            }

            if ($user->isCourier()) {
                return in_array(UserRole::COURIER, $allowed);
            }
        }

        return false;
    }

    private function canEditGlobal(User $user, SettingKey $key): bool
    {
        return $this->canEdit($user, $key, SettingScope::GLOBAL);
    }

    private function canEditRole(User $user, SettingKey $key, UserRole $role): bool
    {
        return $this->canEdit($user, $key, SettingScope::ROLE, $role);
    }

    private function canEditOwnUser(User $user, SettingKey $key): bool
    {
        return $this->canEdit($user, $key, SettingScope::USER, null, $user->id);
    }
}
