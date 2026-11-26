<?php

namespace App\Dto;

use App\Enums\SettingScope;

final class AvailableSettingScope
{
    private function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly SettingScope $scope,
        public readonly ?string $role,
    ) {}

    public static function global(): self
    {
        return new self(
            id: 'global',
            label: 'Глобально',
            scope: SettingScope::GLOBAL,
            role: null,
        );
    }

    public static function forRole(string $roleValue, string $label): self
    {
        return new self(
            id: 'role_'.$roleValue,
            label: $label,
            scope: SettingScope::ROLE,
            role: $roleValue,
        );
    }

    public static function forUser(): self
    {
        return new self(
            id: 'user_me',
            label: 'Только я',
            scope: SettingScope::USER,
            role: null,
        );
    }

    public function toArray(): array
    {
        return [
            'value' => $this->id,
            'label' => $this->label,
        ];
    }
}
