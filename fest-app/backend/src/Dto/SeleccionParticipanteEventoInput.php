<?php

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class SeleccionParticipanteEventoInput
{
    #[Groups(['seleccion_participante_evento:write'])]
    public ?string $evento = null;

    #[Groups(['seleccion_participante_evento:write'])]
    public ?string $usuario = null;

    #[Groups(['seleccion_participante_evento:write'])]
    public ?string $invitado = null;
}
