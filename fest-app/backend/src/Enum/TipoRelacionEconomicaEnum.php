<?php

namespace App\Enum;

enum TipoRelacionEconomicaEnum: string
{
    case INTERNO = 'interno';
    case EXTERNO = 'externo';
    case INVITADO = 'invitado';

    public function label(): string
    {
        return match($this) {
            self::INTERNO => 'Interno',
            self::EXTERNO => 'Externo',
            self::INVITADO => 'Invitado',
        };
    }
}
