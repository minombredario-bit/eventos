<?php

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

class PersonaFamiliarView
{
    #[Groups(['persona_familiar_mia:read'])]
    public ?string $id = null;

    #[Groups(['persona_familiar_mia:read'])]
    public ?string $nombre = null;

    #[Groups(['persona_familiar_mia:read'])]
    public ?string $apellidos = null;

    #[Groups(['persona_familiar_mia:read'])]
    public ?string $nombreCompleto = null;

    #[Groups(['persona_familiar_mia:read'])]
    public ?string $parentesco = null;

    #[Groups(['persona_familiar_mia:read'])]
    public string $tipoPersona = 'adulto';

    #[Groups(['persona_familiar_mia:read'])]
    public mixed $observaciones = null;

    #[Groups(['persona_familiar_mia:read'])]
    public mixed $inscripcion = null;

    #[Groups(['persona_familiar_mia:read'])]
    #[SerializedName('@id')]
    public ?string $iri = null;
}
