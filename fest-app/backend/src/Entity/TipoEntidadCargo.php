<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\TipoEntidadCargoRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_USER')"),
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_SUPERADMIN')"),
        new Patch(security: "is_granted('ROLE_SUPERADMIN')"),
    ],
    normalizationContext: ['groups' => ['tipo_entidad_cargo:read']],
    denormalizationContext: ['groups' => ['tipo_entidad_cargo:write']]
)]
#[ORM\Entity(repositoryClass: TipoEntidadCargoRepository::class)]
#[ORM\Table(name: 'tipo_entidad_cargo')]
#[ORM\UniqueConstraint(name: 'uniq_tipo_entidad_cargo', columns: ['tipo_entidad_id', 'cargo_master_id'])]
class TipoEntidadCargo
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['tipo_entidad_cargo:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: TipoEntidad::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['tipo_entidad_cargo:read', 'tipo_entidad_cargo:write'])]
    #[Assert\NotNull]
    private TipoEntidad $tipoEntidad;

    #[ORM\ManyToOne(targetEntity: CargoMaster::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['tipo_entidad_cargo:read', 'tipo_entidad_cargo:write'])]
    #[Assert\NotNull]
    private CargoMaster $cargoMaster;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['tipo_entidad_cargo:read', 'tipo_entidad_cargo:write'])]
    private bool $activo = true;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->toString();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTipoEntidad(): TipoEntidad
    {
        return $this->tipoEntidad;
    }

    public function setTipoEntidad(TipoEntidad $tipoEntidad): static
    {
        $this->tipoEntidad = $tipoEntidad;

        return $this;
    }

    public function getCargoMaster(): CargoMaster
    {
        return $this->cargoMaster;
    }

    public function setCargoMaster(CargoMaster $cargoMaster): static
    {
        $this->cargoMaster = $cargoMaster;

        return $this;
    }

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): static
    {
        $this->activo = $activo;

        return $this;
    }
}
