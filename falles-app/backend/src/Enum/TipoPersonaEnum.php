<?php

namespace App\Enum;

enum TipoPersonaEnum: string
{
    case ADULTO = 'adulto';
    case INFANTIL = 'infantil';

    public function label(): string
    {
        return match($this) {
            self::ADULTO => 'Adulto',
            self::INFANTIL => 'Infantil',
        };
    }
}
