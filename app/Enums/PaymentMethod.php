<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case TERMINAL = 'terminal';
    case ERIP = 'erip';
    case CREDIT = 'credit';
    case INSTALLMENT = 'installment';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Наличные',
            self::TERMINAL => 'Терминал',
            self::ERIP => 'ЕРИП',
            self::CREDIT => 'Кредит',
            self::INSTALLMENT => 'Рассрочка',
        };
    }
}
