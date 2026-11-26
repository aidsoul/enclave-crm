<?php

namespace App\Enums;

enum LeadType: string
{
    case CALLBACK = 'callback';
    case CONTACT = 'contact';
    case QUESTION = 'question';
    case ONE_CLICK = 'one_click';
    case CONSULTATION = 'consultation';

    public function label(): string
    {
        return match ($this) {
            self::CALLBACK => 'Заказ звонка',
            self::CONTACT => 'Обратная связь',
            self::QUESTION => 'Вопрос по товару',
            self::ONE_CLICK => 'Купить в 1 клик',
            self::CONSULTATION => 'Консультация',
        };
    }
}
