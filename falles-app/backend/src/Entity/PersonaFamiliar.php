<?php

namespace App\Entity;

use App\Repository\PersonaFamiliarRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Enum\TipoPersonaEnum;
use App\Enum\TipoRelacionEconomicaEnum;
use App\Enum\EstadoValidacionEnum;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PersonaFamiliarRepository::class)]
#[ORM\Table(name: 'persona_familiar')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => ['persona-familiar:read']],
    denormalizationContext: ['groups' => ['persona-familiar:write']],
    operations: [
        new Get(),
        new GetCollection(),
        new Post(security: "is_granted('ROLE_USER')"),
        new Patch(security: "is_granted('EDIT', object)"),
        new Delete(security: "is_granted('DELETE', object)"),
    ]
)]
class PersonaFamiliar
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['persona-familiar:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Usuario::class, inversedBy: 'familiares')]
    #[ORM\JoinColumn(nullable: false)]
    private Usuario $usuarioPrincipal;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Groups(['persona-familiar:read', 'persona-familiar:write'])]
    #[Assert\NotBlank]
    private string $nombre;

    #[ORM\Column(type: Types::STRING, length: 150)]
    #[Groups(['persona-familiar:read', 'persona-familiar:write'])]
    #[Assert\NotBlank]
    private string $apellidos;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Groups(['persona-familiar:read', 'persona-familiar:write'])]
    #[Assert\NotBlank]
    private string $parentesco;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: TipoPersonaEnum::class)]
    #[Groups(['persona-familiar:read', 'persona-familiar:write'])]
    #[Assert\NotNull]
    private TipoPersonaEnum $tipoPersona;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['persona-familiar:read', 'persona-familiar:write'])]
    private ?\DateTimeImmutable $fechaNacimiento = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['persona-familiar:read', 'persona-familiar:write'])]
    private ?string $observaciones = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['persona-familiar:read'])]
    private bool $activa = true;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: TipoRelacionEconomicaEnum::class)]
    #[Groups(['persona-familiar:read', 'persona-familiar:write'])]
    private TipoRelacionEconomicaEnum $tipoRelacionEconomica;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: EstadoValidacionEnum::class)]
    #[Groups(['persona-familiar:read', 'persona-familiar:write'])]
    private EstadoValidacionEnum $estadoValidacion;

    #[ORM\ManyToOne(targetEntity: Usuario::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['persona-familiar:read'])]
    private ?Usuario $validadoPor = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['persona-familiar:read'])]
    private ?\DateTimeImmutable $fechaValidacion = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['persona-familiar:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['persona-familiar:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->tipoPersona = TipoPersonaEnum::ADULTO;
        $this->tipoRelacionEconomica = TipoRelacionEconomicaEnum::INTERNO;
        $this->estadoValidacion = EstadoValidacionEnum::PENDIENTE_VALIDACION;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUsuarioPrincipal(): Usuario
    {
        return $this->usuarioPrincipal;
    }

    public function setUsuarioPrincipal(Usuario $usuarioPrincipal): static
    {
        $this->usuarioPrincipal = $usuarioPrincipal;
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

    public function getApellidos(): string
    {
        return $this->apellidos;
    }

    public function setApellidos(string $apellidos): static
    {
        $this->apellidos = $apellidos;
        return $this;
    }

    public function getParentesco(): string
    {
        return $this->parentesco;
    }

    public function setParentesco(string $parentesco): static
    {
        $this->parentesco = $parentesco;
        return $this;
    }

    public function getTipoPersona(): TipoPersonaEnum
    {
        return $this->tipoPersona;
    }

    public function setTipoPersona(TipoPersonaEnum $tipoPersona): static
    {
        $this->tipoPersona = $tipoPersona;
        return $this;
    }

    public function getFechaNacimiento(): ?\DateTimeImmutable
    {
        return $this->fechaNacimiento;
    }

    public function setFechaNacimiento(?\DateTimeImmutable $fechaNacimiento): static
    {
        $this->fechaNacimiento = $fechaNacimiento;
        return $this;
    }

    public function getObservaciones(): ?string
    {
        return $this->observaciones;
    }

    public function setObservaciones(?string $observaciones): static
    {
        $this->observaciones = $observaciones;
        return $this;
    }

    public function isActiva(): bool
    {
        return $this->activa;
    }

    public function setActiva(bool $activa): static
    {
        $this->activa = $activa;
        return $this;
    }

    public function getTipoRelacionEconomica(): TipoRelacionEconomicaEnum
    {
        return $this->tipoRelacionEconomica;
    }

    public function setTipoRelacionEconomica(TipoRelacionEconomicaEnum $tipoRelacionEconomica): static
    {
        $this->tipoRelacionEconomica = $tipoRelacionEconomica;
        return $this;
    }

    public function getEstadoValidacion(): EstadoValidacionEnum
    {
        return $this->estadoValidacion;
    }

    public function setEstadoValidacion(EstadoValidacionEnum $estadoValidacion): static
    {
        $this->estadoValidacion = $estadoValidacion;
        return $this;
    }

    public function getValidadoPor(): ?Usuario
    {
        return $this->validadoPor;
    }

    public function setValidadoPor(?Usuario $validadoPor): static
    {
        $this->validadoPor = $validadoPor;
        return $this;
    }

    public function getFechaValidacion(): ?\DateTimeImmutable
    {
        return $this->fechaValidacion;
    }

    public function setFechaValidacion(?\DateTimeImmutable $fechaValidacion): static
    {
        $this->fechaValidacion = $fechaValidacion;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getNombreCompleto(): string
    {
        return sprintf('%s %s', $this->nombre, $this->apellidos);
    }
}
