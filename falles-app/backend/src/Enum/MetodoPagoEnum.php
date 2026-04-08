<?php

namespace App\Enum;

enum MetodoPagoEnum: string
{
    case EFECTIVO = 'efectivo';
    case TRANSFERENCIA = 'transferencia';
    case BIZUM = 'bizum';
    case TPV = 'tpv';
    case ONLINE = 'online';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match($this) {
            self::EFECTIVO => 'Efectivo',
            self::TRANSFERENCIA => 'Transferencia',
            self::BIZUM => 'Bizum',
            self::TPV => 'TPV',
            self::ONLINE => 'Pago online',
            self::MANUAL => 'Manual',
        };
    }
}
