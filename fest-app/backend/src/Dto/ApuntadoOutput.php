<?php
// src/Dto/ApuntadoOutput.php

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class ApuntadoOutput
{
    public function __construct(
        #[Groups(['apuntado:read'])]
        public readonly string $inscripcionId,

        #[Groups(['apuntado:read'])]
        public readonly string $nombreCompleto,

        /** @var string[] */
        #[Groups(['apuntado:read'])]
        public readonly array $opciones = [],
    ) {}
}
