<?php

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

class InvitadoView
{
    #[Groups(['invitado:read'])]
    public ?string $id = null;

    #[Groups(['invitado:read'])]
    public ?string $nombre = null;

    #[Groups(['invitado:read'])]
    public ?string $apellidos = null;

    #[Groups(['invitado:read'])]
    public ?string $nombreCompleto = null;

    #[Groups(['invitado:read'])]
    public ?string $tipoPersona = null;

    #[Groups(['invitado:read'])]
    public ?string $observaciones = null;

    #[Groups(['invitado:read'])]
    public string $origen = 'invitado';

    #[Groups(['invitado:read'])]
    public bool $esInvitado = true;

    #[Groups(['invitado:read'])]
    #[SerializedName('@id')]
    public ?string $iri = null;
}
