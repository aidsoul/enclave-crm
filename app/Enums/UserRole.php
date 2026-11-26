<?php

namespace App\Enums;

enum UserRole: string
{
    case OWNER = 'owner';
    case MANAGER = 'manager';
    case COURIER = 'courier';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Владелец',
            self::MANAGER => 'Менеджер',
            self::COURIER => 'Курьер',
        };
    }

    public function isOwner(): bool
    {
        return $this === self::OWNER;
    }

    public function isManager(): bool
    {
        return $this === self::MANAGER;
    }

    public function isCourier(): bool
    {
        return $this === self::COURIER;
    }
}
