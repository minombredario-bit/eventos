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
    case ABUELO   = 'abuelo';
    case ABUELA   = 'abuela';
}
