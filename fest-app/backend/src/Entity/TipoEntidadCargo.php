<?php

namespace App\Entity;

use App\Repository\TipoEntidadCargoRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TipoEntidadCargoRepository::class)]
#[ORM\Table(name: 'tipo_entidad_cargo')]
class TipoEntidadCargo
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['tipo_entidad_cargo:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: TipoEntidad::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private TipoEntidad $tipoEntidad;

    #[ORM\ManyToOne(targetEntity: CargoMaster::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['tipo_entidad_cargo:read'])]
    private CargoMaster $cargoMaster;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['tipo_entidad_cargo:read', 'tipo_entidad_cargo:write'])]
    private bool $activo = true;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
    }

    public function getId(): ?string { return $this->id; }

    public function getTipoEntidad(): TipoEntidad { return $this->tipoEntidad; }
    public function setTipoEntidad(TipoEntidad $tipoEntidad): static { $this->tipoEntidad = $tipoEntidad; return $this; }

    public function getCargoMaster(): CargoMaster { return $this->cargoMaster; }
    public function setCargoMaster(CargoMaster $cargoMaster): static { $this->cargoMaster = $cargoMaster; return $this; }

    public function isActivo(): bool { return $this->activo; }
    public function setActivo(bool $activo): static { $this->activo = $activo; return $this; }
}

