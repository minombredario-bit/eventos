<?php

namespace App\Enum;

enum CompatibilidadPersonaActividadEnum: string
{
    case ADULTO = 'adulto';
    case CADETE = 'cadete';
    case INFANTIL = 'infantil';
    case AMBOS = 'ambos';

    public function label(): string
    {
        return match ($this) {
            self::ADULTO => 'Solo adulto',
            self::CADETE => 'Solo cadete',
            self::INFANTIL => 'Solo infantil',
            self::AMBOS => 'Adulto e infantil',
        };
    }
}
