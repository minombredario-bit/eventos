<?php

namespace App\Enum;

enum TipoActividadEnum: string
{
    case ADULTO = 'adulto';
    case INFANTIL = 'infantil';
    case ESPECIAL = 'especial';
    case LIBRE = 'libre';

    public function label(): string
    {
        return match($this) {
            self::ADULTO => 'Adulto',
            self::INFANTIL => 'Infantil',
            self::ESPECIAL => 'Especial',
            self::LIBRE => 'Libre',
        };
    }
}
