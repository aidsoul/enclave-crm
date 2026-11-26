<?php

namespace App\Enums;

enum CourierHandoverStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case DISPUTED = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Ожидает приёма',
            self::COMPLETED => 'Завершено',
            self::CANCELLED => 'Отменено',
            self::DISPUTED => 'С расхождением',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::COMPLETED => 'success',
            self::CANCELLED => 'secondary',
            self::DISPUTED => 'error',
        };
    }
}
