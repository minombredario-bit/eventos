<?php

namespace App\Entity;

use App\Repository\TipoEntidadRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TipoEntidadRepository::class)]
#[ORM\Table(name: 'tipo_entidad')]
class TipoEntidad
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['tipo_entidad:read', 'entidad:read'])]
    private ?string $id = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    #[Groups(['tipo_entidad:read', 'tipo_entidad:write', 'entidad:read', 'entidad:write'])]
    private string $codigo;

    #[ORM\Column(type: 'string', length: 150)]
    #[Groups(['tipo_entidad:read', 'tipo_entidad:write', 'entidad:read', 'entidad:write'])]
    private string $nombre;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['tipo_entidad:read', 'tipo_entidad:write'])]
    private ?string $descripcion = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['tipo_entidad:read', 'tipo_entidad:write'])]
    private bool $activo = true;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
    }

    public function getId(): ?string { return $this->id; }

    public function getCodigo(): string { return $this->codigo; }
    public function setCodigo(string $codigo): static { $this->codigo = $codigo; return $this; }

    public function getNombre(): string { return $this->nombre; }
    public function setNombre(string $nombre): static { $this->nombre = $nombre; return $this; }

    public function getDescripcion(): ?string { return $this->descripcion; }
    public function setDescripcion(?string $descripcion): static { $this->descripcion = $descripcion; return $this; }

    public function isActivo(): bool { return $this->activo; }
    public function setActivo(bool $activo): static { $this->activo = $activo; return $this; }
}

