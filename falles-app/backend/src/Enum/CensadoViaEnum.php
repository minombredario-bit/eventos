<?php

namespace App\Enum;

enum CensadoViaEnum: string
{
    case EXCEL = 'excel';
    case MANUAL = 'manual';
    case INVITACION = 'invitacion';

    public function label(): string
    {
        return match($this) {
            self::EXCEL => 'Excel',
            self::MANUAL => 'Manual',
            self::INVITACION => 'Invitación',
        };
    }
}
