<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\CargoMasterRepository;
use Doctrine\DBAL\Types\Types;
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
    normalizationContext: ['groups' => ['cargo_master:read']],
    denormalizationContext: ['groups' => ['cargo_master:write']]
)]
#[ORM\Entity(repositoryClass: CargoMasterRepository::class)]
#[ORM\Table(name: 'cargo_master')]
class CargoMaster
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['cargo_master:read', 'entidad_cargo:read'])]
    private ?string $id = null;

    #[ORM\Column(type: Types::STRING, length: 120)]
    #[Groups(['cargo_master:read', 'cargo_master:write', 'entidad_cargo:read'])]
    #[Assert\NotBlank]
    private string $nombre;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['cargo_master:read', 'cargo_master:write', 'entidad_cargo:read'])]
    private ?string $codigo = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['cargo_master:read', 'cargo_master:write', 'entidad_cargo:read'])]
    private ?string $descripcion = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['cargo_master:read', 'cargo_master:write', 'entidad_cargo:read'])]
    private bool $computaComoDirectivo = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['cargo_master:read', 'cargo_master:write', 'entidad_cargo:read'])]
    private bool $esRepresentativo = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['cargo_master:read', 'cargo_master:write', 'entidad_cargo:read'])]
    private bool $esInfantil = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['cargo_master:read', 'cargo_master:write', 'entidad_cargo:read'])]
    private bool $infantilEspecial = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['cargo_master:read', 'cargo_master:write', 'entidad_cargo:read'])]
    private bool $activo = true;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    #[Groups(['cargo_master:read', 'cargo_master:write', 'entidad_cargo:read'])]
    private int $ordenJerarquico = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2, options: ['default' => '1.00'])]
    #[Groups(['cargo_master:read', 'cargo_master:write', 'entidad_cargo:read'])]
    #[Assert\PositiveOrZero]
    private string $aniosComputables = '1.00';

    public function __construct()
    {
        $this->id = Uuid::uuid4()->toString();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getCodigo(): ?string
    {
        return $this->codigo;
    }

    public function setCodigo(?string $codigo): static
    {
        $this->codigo = $codigo;

        return $this;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static
    {
        $this->descripcion = $descripcion;

        return $this;
    }

    public function isComputaComoDirectivo(): bool
    {
        return $this->computaComoDirectivo;
    }

    public function setComputaComoDirectivo(bool $computaComoDirectivo): static
    {
        $this->computaComoDirectivo = $computaComoDirectivo;

        return $this;
    }

    public function isEsRepresentativo(): bool
    {
        return $this->esRepresentativo;
    }

    public function setEsRepresentativo(bool $esRepresentativo): static
    {
        $this->esRepresentativo = $esRepresentativo;

        return $this;
    }

    public function isEsInfantil(): bool
    {
        return $this->esInfantil;
    }

    public function setEsInfantil(bool $esInfantil): static
    {
        $this->esInfantil = $esInfantil;

        return $this;
    }

    public function isInfantilEspecial(): bool
    {
        return $this->infantilEspecial;
    }

    public function setInfantilEspecial(bool $infantilEspecial): static
    {
        $this->infantilEspecial = $infantilEspecial;

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

    public function getOrdenJerarquico(): int
    {
        return $this->ordenJerarquico;
    }

    public function setOrdenJerarquico(int $ordenJerarquico): static
    {
        $this->ordenJerarquico = $ordenJerarquico;

        return $this;
    }

    public function getAniosComputables(): float
    {
        return (float) $this->aniosComputables;
    }

    public function setAniosComputables(float $aniosComputables): static
    {
        $this->aniosComputables = number_format($aniosComputables, 2, '.', '');

        return $this;
    }
}
