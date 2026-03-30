<?php

namespace App\Entity;

use App\Repository\InscripcionLineaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use App\Enum\EstadoLineaInscripcionEnum;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InscripcionLineaRepository::class)]
#[ORM\Table(name: 'inscripcion_linea')]
#[ApiResource(
    normalizationContext: ['groups' => ['inscripcion-linea:read']],
    denormalizationContext: ['groups' => ['inscripcion-linea:write']],
    operations: [
        new Get(),
        new Patch(security: "is_granted('ROLE_ADMIN_ENTIDAD')"),
    ]
)]
class InscripcionLinea
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['inscripcion-linea:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Inscripcion::class, inversedBy: 'lineas')]
    #[ORM\JoinColumn(nullable: false)]
    private Inscripcion $inscripcion;

    #[ORM\ManyToOne(targetEntity: Invitado::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    #[Groups(['inscripcion-linea:read', 'inscripcion-linea:write'])]
    private ?Invitado $invitado = null;

    #[ORM\ManyToOne(targetEntity: MenuEvento::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['inscripcion-linea:read', 'inscripcion-linea:write'])]
    #[Assert\NotNull]
    private MenuEvento $menu;

    // Snapshot fields
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['inscripcion-linea:read'])]
    private string $nombrePersonaSnapshot;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Groups(['inscripcion-linea:read'])]
    private string $tipoPersonaSnapshot;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['inscripcion-linea:read'])]
    private ?string $tipoRelacionEconomicaSnapshot = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['inscripcion-linea:read'])]
    private ?string $estadoValidacionSnapshot = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['inscripcion-linea:read'])]
    private string $nombreMenuSnapshot;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Groups(['inscripcion-linea:read'])]
    private string $franjaComidaSnapshot;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['inscripcion-linea:read'])]
    private bool $esDePagoSnapshot;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    #[Groups(['inscripcion-linea:read'])]
    private string $precioUnitario;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: EstadoLineaInscripcionEnum::class)]
    #[Groups(['inscripcion-linea:read', 'inscripcion-linea:write'])]
    private EstadoLineaInscripcionEnum $estadoLinea;

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

    // --- Validación: exactamente una persona o invitado ---

    #[Assert\IsTrue(message: 'La línea debe tener exactamente una persona: familiar o invitado, nunca ambos ni ninguno.')]
    public function isPersonaValida(): bool
    {
        return ($this->persona === null) !== ($this->invitado === null);
    }

    // --- Helper para obtener el nombre independientemente del tipo ---

    public function getNombreParticipante(): string
    {
        if ($this->persona !== null) {
            return $this->persona->getNombreCompleto();
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
        if ($this->persona !== null) {
            $this->nombrePersonaSnapshot          = $this->persona->getNombreCompleto();
            $this->tipoPersonaSnapshot            = $this->persona->getTipoPersona()->value;
            $this->tipoRelacionEconomicaSnapshot  = $this->persona->getTipoRelacionEconomica()->value;
            $this->estadoValidacionSnapshot       = $this->persona->getEstadoValidacion()->value;
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