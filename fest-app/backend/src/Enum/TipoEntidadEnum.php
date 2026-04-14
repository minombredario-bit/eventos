<?php

namespace App\Enum;

enum TipoEntidadEnum: string
{
    case FALLA = 'falla';
    case COMPARSA = 'comparsa';
    case PENYA = 'penya';
    case HERMANDAD = 'hermandad';
    case ASOCIACION = 'asociacion';
    case CLUB = 'club';
    case OTRO = 'otro';

    public function label(): string
    {
        return match($this) {
            self::FALLA => 'Falla',
            self::COMPARSA => 'Comparsa',
            self::PENYA => 'Peña',
            self::HERMANDAD => 'Hermandad',
            self::ASOCIACION => 'Asociación',
            self::CLUB => 'Club',
            self::OTRO => 'Otro',
        };
    }
}
