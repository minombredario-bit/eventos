<?php

namespace App\Enum;

enum FranjaComidaEnum: string
{
    case ALMUERZO = 'almuerzo';
    case COMIDA = 'comida';
    case MERIENDA = 'merienda';
    case CENA = 'cena';

    public function label(): string
    {
        return match ($this) {
            self::ALMUERZO => 'Almuerzo',
            self::COMIDA => 'Comida',
            self::MERIENDA => 'Merienda',
            self::CENA => 'Cena',
        };
    }
}
