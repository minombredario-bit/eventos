<?php

namespace App\Enum;

enum EstadoEventoEnum: string
{
    case BORRADOR = 'borrador';
    case PUBLICADO = 'publicado';
    case CERRADO = 'cerrado';
    case FINALIZADO = 'finalizado';
    case CANCELADO = 'cancelado';

    public function label(): string
    {
        return match($this) {
            self::BORRADOR => 'Borrador',
            self::PUBLICADO => 'Publicado',
            self::CERRADO => 'Cerrado',
            self::FINALIZADO => 'Finalizado',
            self::CANCELADO => 'Cancelado',
        };
    }

    public function admiteInscripciones(): bool
    {
        return $this === self::PUBLICADO;
    }
}
