<?php

namespace App\Entity;

use App\Repository\EntidadCargoRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: EntidadCargoRepository::class)]
#[ORM\Table(name: 'entidad_cargo')]
class EntidadCargo
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['entidad_cargo:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Entidad::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Entidad $entidad;

    #[ORM\ManyToOne(targetEntity: CargoMaster::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['entidad_cargo:read'])]
    private CargoMaster $cargoMaster;

    #[ORM\Column(type: 'string', length: 120, nullable: true)]
    #[Groups(['entidad_cargo:read', 'entidad_cargo:write'])]
    private ?string $nombre = null; // optional override name per entidad

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    #[Groups(['entidad_cargo:read', 'entidad_cargo:write'])]
    private int $orden = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['entidad_cargo:read', 'entidad_cargo:write'])]
    private bool $activo = true;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
    }

    public function getId(): ?string { return $this->id; }

    public function getEntidad(): Entidad { return $this->entidad; }
    public function setEntidad(Entidad $entidad): static { $this->entidad = $entidad; return $this; }

    public function getCargoMaster(): CargoMaster { return $this->cargoMaster; }
    public function setCargoMaster(CargoMaster $cargoMaster): static { $this->cargoMaster = $cargoMaster; return $this; }

    public function getNombre(): ?string { return $this->nombre; }
    public function setNombre(?string $nombre): static { $this->nombre = $nombre; return $this; }

    public function getOrden(): int { return $this->orden; }
    public function setOrden(int $orden): static { $this->orden = $orden; return $this; }

    public function isActivo(): bool { return $this->activo; }
    public function setActivo(bool $activo): static { $this->activo = $activo; return $this; }
}

