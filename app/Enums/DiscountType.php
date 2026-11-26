<?php

namespace App\Enums;

enum DiscountType: string
{
    case PERCENTAGE = 'percentage';
    case FIXED = 'fixed';
    case COUPON = 'coupon';

    public function label(): string
    {
        return match ($this) {
            self::PERCENTAGE => 'Процент',
            self::FIXED => 'Фиксированная',
            self::COUPON => 'Купон',
        };
    }
}
