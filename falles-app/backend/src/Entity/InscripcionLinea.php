<?php

namespace App\Entity;

use App\Repository\InscripcionLineaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use App\Enum\EstadoLineaInscripcionEnum;
use App\State\InscripcionLineaPatchProcessor;
use App\State\InscripcionLineaDeleteProcessor;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InscripcionLineaRepository::class)]
#[ORM\Table(
    name: 'inscripcion_linea',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_inscripcion_linea_usuario_menu', columns: ['usuario_id', 'menu_id']),
        new ORM\UniqueConstraint(name: 'uniq_inscripcion_linea_invitado_menu', columns: ['invitado_id', 'menu_id']),
    ],
)]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('INSCRIPCION_VIEW', object.getInscripcion())"),
        new Delete(
            security: "is_granted('INSCRIPCION_VIEW', object.getInscripcion()) and object.getInscripcion().getEvento().estaInscripcionAbierta()",
            processor: InscripcionLineaDeleteProcessor::class,
        ),
        new Patch(
            denormalizationContext: ['groups' => ['inscripcion-linea:update']],
            security: "is_granted('INSCRIPCION_VIEW', object.getInscripcion()) and object.getInscripcion().getEvento().estaInscripcionAbierta()",
            processor: InscripcionLineaPatchProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['inscripcion-linea:read']],
    denormalizationContext: ['groups' => ['inscripcion-linea:write']]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'inscripcion' => 'exact',
    'inscripcion.id' => 'exact',
    'menu' => 'exact',
    'menu.id' => 'exact',
    'usuario' => 'exact',
    'invitado' => 'exact',
    'estadoLinea' => 'exact',
])]
#[ApiFilter(
    OrderFilter::class,
    properties: ['createdAt', 'precioUnitario'],
    arguments: ['orderParameterName' => 'order']
)]
class InscripcionLinea
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['inscripcion-linea:read', 'inscripcion:read', 'inscripcion:collection'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Inscripcion::class, inversedBy: 'lineas')]
    #[ORM\JoinColumn(name: 'inscripcion_id', nullable: false)]
    private Inscripcion $inscripcion;

    #[ORM\ManyToOne(targetEntity: Invitado::class)]
    #[ORM\JoinColumn(name: 'invitado_id', nullable: true, onDelete: 'RESTRICT')]
    #[Groups(['inscripcion-linea:read', 'inscripcion-linea:write', 'inscripcion:read', 'inscripcion:collection'])]
    private ?Invitado $invitado = null;

    #[ORM\ManyToOne(targetEntity: Usuario::class)]
    #[ORM\JoinColumn(name: 'usuario_id', nullable: true, onDelete: 'RESTRICT')]
    #[Groups(['inscripcion-linea:read', 'inscripcion-linea:write', 'inscripcion:read', 'inscripcion:collection'])]
    private ?Usuario $usuario = null;

    #[ORM\ManyToOne(targetEntity: MenuEvento::class)]
    #[ORM\JoinColumn(name: 'menu_id', nullable: false)]
    #[Groups(['inscripcion-linea:read', 'inscripcion-linea:write', 'inscripcion-linea:update', 'inscripcion:read', 'inscripcion:collection'])]
    #[Assert\NotNull]
    private MenuEvento $menu;

    // Snapshot fields
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['inscripcion-linea:read', 'inscripcion:read', 'inscripcion:collection'])]
    private string $nombrePersonaSnapshot;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Groups(['inscripcion-linea:read', 'inscripcion:read', 'inscripcion:collection'])]
    private string $tipoPersonaSnapshot;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['inscripcion-linea:read'])]
    private ?string $tipoRelacionEconomicaSnapshot = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['inscripcion-linea:read'])]
    private ?string $estadoValidacionSnapshot = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['inscripcion-linea:read', 'inscripcion:read', 'inscripcion:collection'])]
    private string $nombreMenuSnapshot;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Groups(['inscripcion-linea:read', 'inscripcion:read', 'inscripcion:collection'])]
    private string $franjaComidaSnapshot;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['inscripcion-linea:read'])]
    private bool $esDePagoSnapshot;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    #[Groups(['inscripcion-linea:read', 'inscripcion:read', 'inscripcion:collection'])]
    private string $precioUnitario;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: EstadoLineaInscripcionEnum::class)]
    #[Groups(['inscripcion-linea:read', 'inscripcion-linea:write', 'inscripcion-linea:update', 'inscripcion:read', 'inscripcion:collection'])]
    private EstadoLineaInscripcionEnum $estadoLinea;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['inscripcion-linea:read', 'inscripcion:read', 'inscripcion:collection'])]
    private bool $pagada = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['inscripcion-linea:read', 'inscripcion-linea:write'])]
    private ?string $observaciones = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['inscripcion-linea:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id         = Uuid::uuid4();
        $this->createdAt  = new \DateTimeImmutable();
        $this->estadoLinea = EstadoLineaInscripcionEnum::PENDIENTE;
    }

    // --- Validación: exactamente un usuario o invitado ---

    #[Assert\IsTrue(message: 'La línea debe tener exactamente un participante: usuario o invitado, nunca ambos ni ninguno.')]
    public function isParticipanteValido(): bool
    {
        return ($this->usuario === null) !== ($this->invitado === null);
    }

    // --- Helper para obtener el nombre independientemente del tipo ---

    public function getNombreParticipante(): string
    {
        if ($this->usuario !== null) {
            return $this->usuario->getNombreCompleto();
        }

        if ($this->invitado !== null) {
            return $this->invitado->getNombreCompleto();
        }

        return $this->nombrePersonaSnapshot;
    }

    public function esDeInvitado(): bool
    {
        return $this->invitado !== null;
    }

    // --- Snapshot ---

    public function crearSnapshot(): void
    {
        if ($this->usuario !== null) {
            $this->nombrePersonaSnapshot          = $this->usuario->getNombreCompleto();
            $this->tipoPersonaSnapshot            = 'adulto';
            $this->tipoRelacionEconomicaSnapshot  = $this->usuario->getTipoUsuarioEconomico()->value;
            $this->estadoValidacionSnapshot       = $this->usuario->getEstadoValidacion()->value;
        } elseif ($this->invitado !== null) {
            $this->nombrePersonaSnapshot         = $this->invitado->getNombreCompleto();
            $this->tipoPersonaSnapshot           = $this->invitado->getTipoPersona()->value;
            $this->tipoRelacionEconomicaSnapshot = null; // Los invitados no tienen relación económica
            $this->estadoValidacionSnapshot      = null; // Los invitados no tienen validación
        }

        $this->nombreMenuSnapshot  = $this->menu->getNombre();
        $this->franjaComidaSnapshot = $this->menu->getFranjaComida()->value;
        $this->esDePagoSnapshot    = $this->menu->isEsDePago();
    }

    // --- Getters y setters ---

    public function getId(): ?string {
        return $this->id; }

    public function getInscripcion(): Inscripcion
    {
        return $this->inscripcion;
    }

    public function setInscripcion(Inscripcion $inscripcion): static
    {
        $this->inscripcion = $inscripcion; return $this;
    }


    public function getInvitado(): ?Invitado
    {
        return $this->invitado;
    }

    public function getUsuario(): ?Usuario
    {
        return $this->usuario;
    }

    public function setUsuario(?Usuario $usuario): static
    {
        $this->usuario = $usuario;
        return $this;
    }

    public function setInvitado(?Invitado $invitado): static
    {
        $this->invitado = $invitado;
        return $this;
    }

    public function getMenu(): MenuEvento
    {
        return $this->menu;
    }


    public function setMenu(MenuEvento $menu): static
    {
        $this->menu = $menu;
        return $this;
    }

    public function getNombrePersonaSnapshot(): string
    {
        return $this->nombrePersonaSnapshot;
    }

    public function getTipoPersonaSnapshot(): string
    {
        return $this->tipoPersonaSnapshot;
    }

    public function getTipoRelacionEconomicaSnapshot(): ?string
    {
        return $this->tipoRelacionEconomicaSnapshot;
    }

    public function getEstadoValidacionSnapshot(): ?string
    {
        return $this->estadoValidacionSnapshot;
    }

    public function getNombreMenuSnapshot(): string
    {
        return $this->nombreMenuSnapshot;
    }

    public function getFranjaComidaSnapshot(): string
    {
        return $this->franjaComidaSnapshot;
    }

    public function isEsDePagoSnapshot(): bool
    {
        return $this->esDePagoSnapshot;
    }

    public function getPrecioUnitario(): float
    {
        return (float) $this->precioUnitario;
    }

    public function setPrecioUnitario(float $precioUnitario): static
    {
        $this->precioUnitario = (string) $precioUnitario;
        return $this;
    }

    public function getEstadoLinea(): EstadoLineaInscripcionEnum
    {
        return $this->estadoLinea;
    }

    public function setEstadoLinea(EstadoLineaInscripcionEnum $estadoLinea): static
    {
        $this->estadoLinea = $estadoLinea;
        return $this;
    }

    public function isPagada(): bool
    {
        return $this->pagada;
    }

    public function setPagada(bool $pagada): static
    {
        $this->pagada = $pagada;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
