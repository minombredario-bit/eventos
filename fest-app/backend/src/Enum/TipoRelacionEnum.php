<?php

namespace App\Enum;

enum TipoRelacionEnum: string
{
    case CONYUGE  = 'conyuge';
    case PADRE    = 'padre';
    case MADRE    = 'madre';
    case PAREJA   = 'pareja';
    case HIJO     = 'hijo';
    case HIJA     = 'hija';
    case SOBRINO  = 'sobrino';
    case SOBRINA  = 'sobrina';
    case TIO      = 'tio';
    case TIA      = 'tia';
    case ABUELO   = 'abuelo';
    case ABUELA   = 'abuela';

    public function label(): string
    {
        return match ($this) {
            self::CONYUGE => 'Cónyuge',
            self::PADRE => 'Padre',
            self::MADRE => 'Madre',
            self::PAREJA => 'Pareja',
            self::HIJO => 'Hijo',
            self::HIJA => 'Hija',
            self::SOBRINO => 'Sobrino',
            self::SOBRINA => 'Sobrina',
            self::TIO => 'Tío',
            self::TIA => 'Tía',
            self::ABUELO => 'Abuelo',
            self::ABUELA => 'Abuela',
        };
    }
}
