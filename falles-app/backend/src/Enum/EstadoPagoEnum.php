<?php

namespace App\Enum;

enum EstadoPagoEnum: string
{
    case NO_REQUIERE_PAGO = 'no_requiere_pago';
    case PENDIENTE = 'pendiente';
    case PARCIAL = 'parcial';
    case PAGADO = 'pagado';
    case DEVUELTO = 'devuelto';
    case CANCELADO = 'cancelado';

    public function label(): string
    {
        return match($this) {
            self::NO_REQUIERE_PAGO => 'No requiere pago',
            self::PENDIENTE => 'Pendiente',
            self::PARCIAL => 'Pago parcial',
            self::PAGADO => 'Pagado',
            self::DEVUELTO => 'Devuelto',
            self::CANCELADO => 'Cancelado',
        };
    }

    public function estaCompletado(): bool
    {
        return $this === self::PAGADO || $this === self::NO_REQUIERE_PAGO;
    }
}
