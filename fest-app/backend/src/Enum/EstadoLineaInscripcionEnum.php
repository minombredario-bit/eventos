<?php

namespace App\Enum;

enum EstadoLineaInscripcionEnum: string
{
    case PENDIENTE = 'pendiente';
    case CONFIRMADA = 'confirmada';
    case CANCELADA = 'cancelada';

    public function label(): string
    {
        return match($this) {
            self::PENDIENTE => 'Pendiente',
            self::CONFIRMADA => 'Confirmada',
            self::CANCELADA => 'Cancelada',
        };
    }
}
