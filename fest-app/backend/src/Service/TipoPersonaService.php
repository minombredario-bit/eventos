<?php

namespace App\Service;

use App\Enum\TipoPersonaEnum;

class TipoPersonaService
{
    public function resolverPorEdad(
        \DateTimeImmutable $fechaNacimiento,
        \DateTimeImmutable $fechaReferencia
    ): TipoPersonaEnum {
        $edad = $fechaNacimiento->diff($fechaReferencia)->y;

        return match (true) {
            $edad < 14 => TipoPersonaEnum::INFANTIL,
            $edad < 18 => TipoPersonaEnum::CADETE,
            default => TipoPersonaEnum::ADULTO,
        };
    }
}
