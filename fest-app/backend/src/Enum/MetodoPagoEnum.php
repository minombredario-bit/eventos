<?php

namespace App\Enum;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

enum MetodoPagoEnum: string
{
    case EFECTIVO = 'efectivo';
    case TRANSFERENCIA = 'transferencia';
    case BIZUM = 'bizum';
    case TPV = 'tpv';
    case ONLINE = 'online';
    case MANUAL = 'manual';
    case TARJETA = 'tarjeta';

    public static function fromInput(string $value): self
    {
        return match (strtolower(trim($value))) {
            'efectivo' => self::EFECTIVO,
            'tarjeta' => self::TARJETA,
            'transferencia' => self::TRANSFERENCIA,
            'bizum' => self::BIZUM,
            'tpv' => self::TPV,
            'online' => self::ONLINE,
            'manual' => self::MANUAL,
            default => throw new BadRequestHttpException('formaPagoPreferida no válida.'),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::EFECTIVO => 'Efectivo',
            self::TRANSFERENCIA => 'Transferencia',
            self::BIZUM => 'Bizum',
            self::TARJETA => 'Tarjeta',
            self::TPV => 'TPV',
            self::ONLINE => 'Pago online',
            self::MANUAL => 'Manual',
        };
    }
}
