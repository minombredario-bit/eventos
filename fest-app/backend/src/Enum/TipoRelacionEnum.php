<?php

namespace App\Enum;

enum TipoRelacionEnum: string
{
    case AMISTAD  = 'amistad';
    case FAMILIAR = 'familiar';

    public function label(): string
    {
        return match ($this) {
            self::AMISTAD => 'Amistad',
            self::FAMILIAR => 'Familiar'
        };
    }
}
