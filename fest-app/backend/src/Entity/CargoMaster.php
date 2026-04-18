<?php

namespace App\Entity;

use App\Repository\CargoMasterRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CargoMasterRepository::class)]
#[ORM\Table(name: 'cargo_master')]
class CargoMaster
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['cargo:read', 'cargo_master:read'])]
    private ?string $id = null;

    #[ORM\Column(type: 'string', length: 120)]
    #[Groups(['cargo:read', 'cargo_master:read', 'cargo_master:write'])]
    private string $nombre;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Groups(['cargo_master:read', 'cargo_master:write'])]
    private ?string $codigo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['cargo_master:read', 'cargo_master:write'])]
    private ?string $descripcion = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['cargo_master:read', 'cargo_master:write'])]
    private bool $activo = true;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
    }

    public function getId(): ?string { return $this->id; }

    public function getNombre(): string { return $this->nombre; }
    public function setNombre(string $nombre): static { $this->nombre = $nombre; return $this; }

    public function getCodigo(): ?string { return $this->codigo; }
    public function setCodigo(?string $codigo): static { $this->codigo = $codigo; return $this; }

    public function getDescripcion(): ?string { return $this->descripcion; }
    public function setDescripcion(?string $descripcion): static { $this->descripcion = $descripcion; return $this; }

    public function isActivo(): bool { return $this->activo; }
    public function setActivo(bool $activo): static { $this->activo = $activo; return $this; }
}

