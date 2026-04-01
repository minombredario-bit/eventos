<?php

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class SeleccionParticipantesView
{
    #[Groups(['seleccion_participantes_evento_endpoint:read'])]
    public ?string $eventoId = null;

    /**
     * @var list<array<string, mixed>>
     */
    #[Groups(['seleccion_participantes_evento_endpoint:read'])]
    public array $participantes = [];

    #[Groups(['seleccion_participantes_evento_endpoint:read'])]
    public ?string $updatedAt = null;
}
