<?php

namespace App\Enums;

enum ActionType: string
{
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case STATUS_CHANGE = 'status_change';
    case ASSIGN = 'assign';
    case PANIC = 'panic';
    case AUTH = 'auth';
    case EXPORT = 'export';

    public function label(): string
    {
        return match ($this) {
            self::CREATE => 'Создан',
            self::UPDATE => 'Обновлён',
            self::DELETE => 'Удалён',
            self::STATUS_CHANGE => 'Смена статуса',
            self::ASSIGN => 'Назначение',
            self::PANIC => 'Паника',
            self::AUTH => 'Авторизация',
            self::EXPORT => 'Экспорт',
        };
    }
}
