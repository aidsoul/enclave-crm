<?php

namespace App\Enums;

enum LeadStatus: string
{
    case NEW = 'new';
    case IN_PROGRESS = 'in_progress';
    case CONVERTED = 'converted';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'Новая',
            self::IN_PROGRESS => 'В работе',
            self::CONVERTED => 'Конвертирован в заказ',
            self::REJECTED => 'Отклонён',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NEW => 'primary',
            self::IN_PROGRESS => 'warning',
            self::CONVERTED => 'success',
            self::REJECTED => 'error',
        };
    }
}
