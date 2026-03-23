<?php

namespace App\Enum;

enum EstadoValidacionEnum: string
{
    case PENDIENTE_VALIDACION = 'pendiente_validacion';
    case VALIDADO = 'validado';
    case RECHAZADO = 'rechazado';
    case BLOQUEADO = 'bloqueado';

    public function label(): string
    {
        return match($this) {
            self::PENDIENTE_VALIDACION => 'Pendiente de validación',
            self::VALIDADO => 'Validado',
            self::RECHAZADO => 'Rechazado',
            self::BLOQUEADO => 'Bloqueado',
        };
    }

    public function puedeAcceder(): bool
    {
        return $this === self::VALIDADO;
    }
}
