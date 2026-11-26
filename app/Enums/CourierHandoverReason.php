<?php

namespace App\Enums;

enum CourierHandoverReason: string
{
    case VEHICLE_BREAKDOWN = 'vehicle_breakdown';
    case ACCIDENT = 'accident';
    case HEALTH = 'health';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::VEHICLE_BREAKDOWN => 'Поломка транспорта',
            self::ACCIDENT => 'ДТП',
            self::HEALTH => 'Ухудшение самочувствия',
            self::OTHER => 'Другое',
        };
    }
}
