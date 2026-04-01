<?php

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

class NoFalleroView
{
    #[Groups(['no_fallero:read'])]
    public ?string $id = null;

    #[Groups(['no_fallero:read'])]
    public ?string $nombre = null;

    #[Groups(['no_fallero:read'])]
    public ?string $apellidos = null;

    #[Groups(['no_fallero:read'])]
    public ?string $nombreCompleto = null;

    #[Groups(['no_fallero:read'])]
    public ?string $tipoPersona = null;

    #[Groups(['no_fallero:read'])]
    public ?string $observaciones = null;

    #[Groups(['no_fallero:read'])]
    public string $origen = 'no_fallero';

    #[Groups(['no_fallero:read'])]
    public bool $esNoFallero = true;

    #[Groups(['no_fallero:read'])]
    #[SerializedName('@id')]
    public ?string $iri = null;
}
