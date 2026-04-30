<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\CompatibilidadPersonaActividadEnum;
use App\Enum\FranjaComidaEnum;
use App\Enum\TipoActividadEnum;
use App\Enum\TipoPersonaEnum;
use App\Repository\ActividadEventoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ActividadEventoRepository::class)]
#[ORM\Table(name: 'actividad_evento')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('EVENTO_VIEW', object.getEvento())"),
        new GetCollection(security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN_ENTIDAD')"),

        new Get(
            uriTemplate: '/actividad_eventos/{id}',
            security: "is_granted('EVENTO_VIEW', object.getEvento())"
        ),
        new GetCollection(
            uriTemplate: '/actividad_eventos',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN_ENTIDAD')"
        ),

        # new, more natural aliases: /actividades
        new Get(
            uriTemplate: '/actividades/{id}',
            security: "is_granted('EVENTO_VIEW', object.getEvento())"
        ),
        new GetCollection(
            uriTemplate: '/actividades',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN_ENTIDAD')"
        ),

        new Post(
            security: "is_granted('ROLE_ADMIN_ENTIDAD') or is_granted('ROLE_SUPERADMIN')",
            securityPostDenormalize: "is_granted('EVENTO_EDIT', object.getEvento())"
        ),
        new Post(
            uriTemplate: '/actividad_eventos',
            security: "is_granted('ROLE_ADMIN_ENTIDAD') or is_granted('ROLE_SUPERADMIN')",
            securityPostDenormalize: "is_granted('EVENTO_EDIT', object.getEvento())"
        ),
        new Patch(security: "is_granted('EVENTO_EDIT', object.getEvento())"),

        new Delete(security: "is_granted('ROLE_ADMIN_ENTIDAD') or is_granted('ROLE_SUPERADMIN')"),

        new Patch(
            uriTemplate: '/actividad_eventos/{id}',
            security: "is_granted('EVENTO_EDIT', object.getEvento())"
        ),
    ],
    normalizationContext: ['groups' => ['actividad-evento:read']],
    denormalizationContext: ['groups' => ['actividad-evento:write']]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'evento' => 'exact',
    'evento.id' => 'exact',
    'tipoActividad' => 'exact',
    'franjaComida' => 'exact',
    'compatibilidadPersona' => 'exact',
    'nombre' => 'partial',
])]
#[ApiFilter(BooleanFilter::class, properties: ['esDePago', 'activo', 'confirmacionAutomatica'])]
#[ApiFilter(
    OrderFilter::class,
    properties: ['ordenVisualizacion', 'precioBase', 'createdAt', 'updatedAt'],
    arguments: ['orderParameterName' => 'order']
)]
class ActividadEvento
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['actividad-evento:read', 'actividad-evento:evento:item:min', 'evento:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Evento::class, inversedBy: 'actividades')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['actividad-evento:read', 'actividad-evento:write'])]
    #[Assert\NotNull]
    private Evento $evento;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['actividad-evento:read', 'actividad-evento:write', 'actividad-evento:evento:item:min', 'evento:write'])]
    #[Assert\NotBlank]
    private string $nombre;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['actividad-evento:read', 'actividad-evento:write', 'evento:write'])]
    private ?string $descripcion = null;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: TipoActividadEnum::class)]
    #[Groups(['actividad-evento:read', 'actividad-evento:write', 'actividad-evento:evento:item:min', 'evento:write'])]
    #[Assert\NotNull]
    private TipoActividadEnum $tipoActividad;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: FranjaComidaEnum::class)]
    #[Groups(['actividad-evento:read', 'actividad-evento:write', 'actividad-evento:evento:item:min', 'evento:write'])]
    #[Assert\NotNull]
    private FranjaComidaEnum $franjaComida;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: CompatibilidadPersonaActividadEnum::class)]
    #[Groups(['actividad-evento:read', 'actividad-evento:write', 'actividad-evento:evento:item:min', 'evento:write'])]
    #[Assert\NotNull]
    private CompatibilidadPersonaActividadEnum $compatibilidadPersona;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['actividad-evento:read', 'actividad-evento:write', 'actividad-evento:evento:item:min', 'evento:write'])]
    private bool $esDePago = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['actividad-evento:read', 'actividad-evento:write', 'evento:write', 'actividad-evento:evento:item:min'])]
    private bool $permiteInvitados = true;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    #[Groups(['actividad-evento:read', 'actividad-evento:write', 'actividad-evento:evento:item:min', 'evento:write'])]
    // Allow serializer to accept numeric values (int/float) or strings coming from JSON
    private string|float $precioBase = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    #[Groups(['actividad-evento:read', 'actividad-evento:write', 'actividad-evento:evento:item:min', 'evento:write'])]
    private string|float|null $precioAdultoInterno = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    #[Groups(['actividad-evento:read', 'actividad-evento:write', 'actividad-evento:evento:item:min', 'evento:write'])]
    private string|float|null $precioAdultoExterno = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    #[Groups(['actividad-evento:read', 'actividad-evento:write', 'actividad-evento:evento:item:min', 'evento:write'])]
    private string|float|null $precioInfantil = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['actividad-evento:read', 'actividad-evento:write', 'evento:write'])]
    private ?int $unidadesMaximas = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['actividad-evento:read', 'actividad-evento:write', 'actividad-evento:evento:item:min', 'evento:write'])]
    private int $ordenVisualizacion = 0;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['actividad-evento:read', 'actividad-evento:write', 'evento:write'])]
    private bool $activo = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['actividad-evento:read', 'actividad-evento:write'])]
    private bool $confirmacionAutomatica = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observacionesInternas = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['actividad-evento:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['actividad-evento:read'])]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    #[Groups(['actividad-evento:read', 'actividad-evento:write', 'actividad-evento:evento:item:min', 'evento:write'])]
    private string|float|null $precioInfantilExterno = null;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->franjaComida = FranjaComidaEnum::COMIDA;
        $this->compatibilidadPersona = CompatibilidadPersonaActividadEnum::AMBOS;
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

    public function getEvento(): Evento
    {
        return $this->evento;
    }

    public function setEvento(Evento $evento): static
    {
        $this->evento = $evento;
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

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static
    {
        $this->descripcion = $descripcion;
        return $this;
    }

    public function getTipoActividad(): TipoActividadEnum
    {
        return $this->tipoActividad;
    }

    public function setTipoActividad(TipoActividadEnum $tipoActividad): static
    {
        $this->tipoActividad = $tipoActividad;
        return $this;
    }

    public function getFranjaComida(): FranjaComidaEnum
    {
        return $this->franjaComida;
    }

    public function setFranjaComida(FranjaComidaEnum $franjaComida): static
    {
        $this->franjaComida = $franjaComida;
        return $this;
    }

    public function getCompatibilidadPersona(): CompatibilidadPersonaActividadEnum
    {
        return $this->compatibilidadPersona;
    }

    public function setCompatibilidadPersona(CompatibilidadPersonaActividadEnum $compatibilidadPersona): static
    {
        $this->compatibilidadPersona = $compatibilidadPersona;
        return $this;
    }

    public function esCompatibleConTipoPersona(TipoPersonaEnum $tipoPersona): bool
    {
        return match ($this->compatibilidadPersona) {
            CompatibilidadPersonaActividadEnum::AMBOS => true,
            CompatibilidadPersonaActividadEnum::ADULTO,
            CompatibilidadPersonaActividadEnum::CADETE => $tipoPersona === TipoPersonaEnum::ADULTO,
            CompatibilidadPersonaActividadEnum::INFANTIL => $tipoPersona === TipoPersonaEnum::INFANTIL,
        };
    }

    public function isEsDePago(): bool
    {
        return $this->esDePago;
    }

    public function setEsDePago(bool $esDePago): static
    {
        $this->esDePago = $esDePago;
        return $this;
    }

    public function isPermiteInvitados(): bool
    {
        return $this->permiteInvitados;
    }

    public function setPermiteInvitados(bool $permiteInvitados): static
    {
        $this->permiteInvitados = $permiteInvitados;
        return $this;
    }

    public function getPrecioBase(): float
    {
        return (float) $this->precioBase;
    }

    public function setPrecioBase(float $precioBase): static
    {
        $this->precioBase = (string) $precioBase;
        return $this;
    }

    public function getPrecioAdultoInterno(): ?float
    {
        return $this->precioAdultoInterno !== null ? (float) $this->precioAdultoInterno : null;
    }

    public function setPrecioAdultoInterno(?float $precioAdultoInterno): static
    {
        $this->precioAdultoInterno = $precioAdultoInterno !== null ? (string) $precioAdultoInterno : null;
        return $this;
    }

    public function getPrecioAdultoExterno(): ?float
    {
        return $this->precioAdultoExterno !== null ? (float) $this->precioAdultoExterno : null;
    }

    public function setPrecioAdultoExterno(?float $precioAdultoExterno): static
    {
        $this->precioAdultoExterno = $precioAdultoExterno !== null ? (string) $precioAdultoExterno : null;
        return $this;
    }

    public function getPrecioInfantil(): ?float
    {
        return $this->precioInfantil !== null ? (float) $this->precioInfantil : null;
    }

    public function setPrecioInfantil(?float $precioInfantil): static
    {
        $this->precioInfantil = $precioInfantil !== null ? (string) $precioInfantil : null;
        return $this;
    }

    public function getUnidadesMaximas(): ?int
    {
        return $this->unidadesMaximas;
    }

    public function setUnidadesMaximas(?int $unidadesMaximas): static
    {
        $this->unidadesMaximas = $unidadesMaximas;
        return $this;
    }

    public function getOrdenVisualizacion(): int
    {
        return $this->ordenVisualizacion;
    }

    public function setOrdenVisualizacion(int $ordenVisualizacion): static
    {
        $this->ordenVisualizacion = $ordenVisualizacion;
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

    public function isConfirmacionAutomatica(): bool
    {
        return $this->confirmacionAutomatica;
    }

    public function setConfirmacionAutomatica(bool $confirmacionAutomatica): static
    {
        $this->confirmacionAutomatica = $confirmacionAutomatica;
        return $this;
    }

    public function getObservacionesInternas(): ?string
    {
        return $this->observacionesInternas;
    }

    public function setObservacionesInternas(?string $observacionesInternas): static
    {
        $this->observacionesInternas = $observacionesInternas;
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

    public function getPrecioInfantilExterno(): ?float
    {
        return $this->precioInfantilExterno !== null ? (float) $this->precioInfantilExterno : null;
    }

    public function setPrecioInfantilExterno(?float $precioInfantilExterno): static
    {
        $this->precioInfantilExterno = $precioInfantilExterno !== null ? (string) $precioInfantilExterno : null;
        return $this;
    }
}
