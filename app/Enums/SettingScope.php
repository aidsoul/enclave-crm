<?php

namespace App\Enums;

enum SettingScope: string
{
    case GLOBAL = 'global';
    case ROLE = 'role';
    case USER = 'user';

    public function label(): string
    {
        return match ($this) {
            self::GLOBAL => 'Глобально',
            self::ROLE => 'Настроено для роли',
            self::USER => 'Личное значение',
        };
    }

    public function storageScope(): string
    {
        return $this->value;
    }
}
