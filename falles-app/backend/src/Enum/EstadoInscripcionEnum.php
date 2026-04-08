<?php

namespace App\Enum;

enum EstadoInscripcionEnum: string
{
    case PENDIENTE = 'pendiente';
    case CONFIRMADA = 'confirmada';
    case CANCELADA = 'cancelada';
    case LISTA_ESPERA = 'lista_espera';

    public function label(): string
    {
        return match($this) {
            self::PENDIENTE => 'Pendiente',
            self::CONFIRMADA => 'Confirmada',
            self::CANCELADA => 'Cancelada',
            self::LISTA_ESPERA => 'Lista de espera',
        };
    }
}
