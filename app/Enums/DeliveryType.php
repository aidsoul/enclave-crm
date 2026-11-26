<?php

namespace App\Enums;

enum DeliveryType: string
{
    case COURIER = 'courier';
    case PICKUP = 'pickup';

    public function label(): string
    {
        return match ($this) {
            self::COURIER => 'Курьер',
            self::PICKUP => 'Самовывоз',
        };
    }
}
