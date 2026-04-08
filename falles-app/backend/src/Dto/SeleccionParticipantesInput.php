<?php

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class SeleccionParticipantesInput
{
    /**
     * @var list<array<string, mixed>>
     */
    #[Groups(['seleccion_participantes_evento_endpoint:write'])]
    public array $participantes = [];
}
