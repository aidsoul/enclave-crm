<?php

namespace App\Enums;

enum OrderStatus: string
{
    case CREATED = 'created';
    case ACCEPTED = 'accepted';
    case ASSIGNED = 'assigned';
    case AT_WAREHOUSE = 'at_warehouse';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Создан',
            self::ACCEPTED => 'Принят',
            self::ASSIGNED => 'Передан курьеру',
            self::AT_WAREHOUSE => 'На складе',
            self::CANCELLED => 'Отменён',
            self::COMPLETED => 'Завершён',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CREATED => 'primary',
            self::ACCEPTED => 'info',
            self::ASSIGNED => 'secondary',
            self::AT_WAREHOUSE => 'warning',
            self::CANCELLED => 'error',
            self::COMPLETED => 'success',
        };
    }

    public function getAvailableStatuses(): array
    {
        return match ($this) {
            self::CREATED => [self::ACCEPTED, self::ASSIGNED, self::AT_WAREHOUSE, self::CANCELLED, self::COMPLETED],
            self::ACCEPTED => [self::ASSIGNED, self::AT_WAREHOUSE, self::CANCELLED, self::COMPLETED],
            self::ASSIGNED => [self::AT_WAREHOUSE, self::CANCELLED, self::COMPLETED],
            self::AT_WAREHOUSE => [self::ASSIGNED, self::CANCELLED, self::COMPLETED, self::CREATED],
            self::CANCELLED => [self::CREATED],
            self::COMPLETED => [self::AT_WAREHOUSE],
        };
    }
}
