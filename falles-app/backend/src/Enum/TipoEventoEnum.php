<?php

namespace App\Enum;

enum TipoEventoEnum: string
{
    case ALMUERZO = 'almuerzo';
    case COMIDA = 'comida';
    case MERIENDA = 'merienda';
    case CENA = 'cena';
    case OTRO = 'otro';

    public function label(): string
    {
        return match($this) {
            self::ALMUERZO => 'Almuerzo',
            self::COMIDA => 'Comida',
            self::MERIENDA => 'Merienda',
            self::CENA => 'Cena',
            self::OTRO => 'Otro',
        };
    }
}
