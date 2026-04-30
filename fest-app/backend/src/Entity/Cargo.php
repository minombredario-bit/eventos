<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\CargoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CargoRepository::class)]
#[ORM\Table(name: 'cargo')]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN_ENTIDAD')"),
        new GetCollection(
            normalizationContext: ['groups' => ['cargo:collection']],
            security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN_ENTIDAD')",
        ),
        new Post(security: "is_granted('ROLE_ADMIN_ENTIDAD')"),
        new Patch(security: "is_granted('ROLE_ADMIN_ENTIDAD')"),
    ],
    normalizationContext: ['groups' => ['cargo:read']],
    denormalizationContext: ['groups' => ['cargo:write']]
)]
class Cargo
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['cargo:read', 'usuario:read', 'cargo:collection', 'entidad_cargo:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Entidad::class, inversedBy: 'cargos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['cargo:read', 'cargo:write'])]
    #[Assert\NotNull]
    private Entidad $entidad;

    #[ORM\Column(type: Types::STRING, length: 120)]
    #[Groups(['cargo:read', 'cargo:write', 'usuario:read', 'cargo:collection', 'entidad_cargo:read'])]
    #[Assert\NotBlank]
    private string $nombre;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['cargo:read', 'cargo:write', 'entidad_cargo:read'])]
    private ?string $codigo = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['cargo:read', 'cargo:write', 'entidad_cargo:read'])]
    private ?string $descripcion = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['cargo:read', 'cargo:write', 'entidad_cargo:read'])]
    private bool $computaComoDirectivo = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['cargo:read', 'cargo:write', 'entidad_cargo:read'])]
    private bool $esRepresentativo = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['cargo:read', 'cargo:write', 'entidad_cargo:read'])]
    private bool $esInfantil = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['cargo:read', 'cargo:write', 'entidad_cargo:read'])]
    private bool $infantilEspecial = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['cargo:read', 'cargo:write', 'entidad_cargo:read'])]
    private bool $activo = true;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    #[Groups(['cargo:read', 'cargo:write', 'entidad_cargo:read'])]
    private int $ordenJerarquico = 0;

    /**
     * Los cargos internos siempre computan 1 año. Es una regla de negocio fija,
     * no un dato configurable, por lo que no se persiste en base de datos.
     */
    #[Groups(['cargo:read', 'cargo:collection', 'entidad_cargo:read'])]
    private float $aniosComputables = 1.0;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->toString();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getEntidad(): Entidad
    {
        return $this->entidad;
    }

    public function setEntidad(Entidad $entidad): static
    {
        $this->entidad = $entidad;

        return $this;
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
        return $this->aniosComputables;
    }

}
