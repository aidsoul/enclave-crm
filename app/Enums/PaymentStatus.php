<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Ожидает оплаты',
            self::PAID => 'Оплачен',
            self::FAILED => 'Неудача',
        };
    }
}
