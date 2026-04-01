<?php

namespace App\Entity;

use App\Repository\MenuEventoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use App\Enum\CompatibilidadPersonaMenuEnum;
use App\Enum\FranjaComidaEnum;
use App\Enum\TipoMenuEnum;
use App\Enum\TipoPersonaEnum;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MenuEventoRepository::class)]
#[ORM\Table(name: 'menu_evento')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => ['menu-evento:read']],
    denormalizationContext: ['groups' => ['menu-evento:write']],
    operations: [
        new Get(),
        new GetCollection(),
        new Post(security: "is_granted('ROLE_ADMIN_ENTIDAD')"),
        new Patch(security: "is_granted('ROLE_ADMIN_ENTIDAD')"),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'evento' => 'exact',
    'evento.id' => 'exact',
    'tipoMenu' => 'exact',
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
class MenuEvento
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['menu-evento:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Evento::class, inversedBy: 'menus')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['menu-evento:read', 'menu-evento:write'])]
    #[Assert\NotNull]
    private Evento $evento;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['menu-evento:read', 'menu-evento:write'])]
    #[Assert\NotBlank]
    private string $nombre;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['menu-evento:read', 'menu-evento:write'])]
    private ?string $descripcion = null;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: TipoMenuEnum::class)]
    #[Groups(['menu-evento:read', 'menu-evento:write'])]
    #[Assert\NotNull]
    private TipoMenuEnum $tipoMenu;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: FranjaComidaEnum::class)]
    #[Groups(['menu-evento:read', 'menu-evento:write'])]
    #[Assert\NotNull]
    private FranjaComidaEnum $franjaComida;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: CompatibilidadPersonaMenuEnum::class)]
    #[Groups(['menu-evento:read', 'menu-evento:write'])]
    #[Assert\NotNull]
    private CompatibilidadPersonaMenuEnum $compatibilidadPersona;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['menu-evento:read', 'menu-evento:write'])]
    private bool $esDePago = true;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    #[Groups(['menu-evento:read', 'menu-evento:write'])]
    private string $precioBase = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    #[Groups(['menu-evento:read', 'menu-evento:write'])]
    private ?string $precioAdultoInterno = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    #[Groups(['menu-evento:read', 'menu-evento:write'])]
    private ?string $precioAdultoExterno = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    #[Groups(['menu-evento:read', 'menu-evento:write'])]
    private ?string $precioInfantil = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['menu-evento:read', 'menu-evento:write'])]
    private ?int $unidadesMaximas = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['menu-evento:read', 'menu-evento:write'])]
    private int $ordenVisualizacion = 0;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['menu-evento:read', 'menu-evento:write'])]
    private bool $activo = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['menu-evento:read', 'menu-evento:write'])]
    private bool $confirmacionAutomatica = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observacionesInternas = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['menu-evento:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['menu-evento:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->franjaComida = FranjaComidaEnum::COMIDA;
        $this->compatibilidadPersona = CompatibilidadPersonaMenuEnum::AMBOS;
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

    public function getTipoMenu(): TipoMenuEnum
    {
        return $this->tipoMenu;
    }

    public function setTipoMenu(TipoMenuEnum $tipoMenu): static
    {
        $this->tipoMenu = $tipoMenu;
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

    public function getCompatibilidadPersona(): CompatibilidadPersonaMenuEnum
    {
        return $this->compatibilidadPersona;
    }

    public function setCompatibilidadPersona(CompatibilidadPersonaMenuEnum $compatibilidadPersona): static
    {
        $this->compatibilidadPersona = $compatibilidadPersona;
        return $this;
    }

    public function esCompatibleConTipoPersona(TipoPersonaEnum $tipoPersona): bool
    {
        return match ($this->compatibilidadPersona) {
            CompatibilidadPersonaMenuEnum::AMBOS => true,
            CompatibilidadPersonaMenuEnum::ADULTO => $tipoPersona === TipoPersonaEnum::ADULTO,
            CompatibilidadPersonaMenuEnum::INFANTIL => $tipoPersona === TipoPersonaEnum::INFANTIL,
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
}
