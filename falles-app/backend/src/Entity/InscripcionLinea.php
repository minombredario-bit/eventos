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

    #[ORM\ManyToOne(targetEntity: PersonaFamiliar::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['inscripcion-linea:read', 'inscripcion-linea:write'])]
    #[Assert\NotNull]
    private PersonaFamiliar $persona;

    #[ORM\ManyToOne(targetEntity: MenuEvento::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['inscripcion-linea:read', 'inscripcion-linea:write'])]
    #[Assert\NotNull]
    private MenuEvento $menu;

    // Snapshot fields (immutable after creation)
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['inscripcion-linea:read'])]
    private string $nombrePersonaSnapshot;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Groups(['inscripcion-linea:read'])]
    private string $tipoPersonaSnapshot;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Groups(['inscripcion-linea:read'])]
    private string $tipoRelacionEconomicaSnapshot;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Groups(['inscripcion-linea:read'])]
    private string $estadoValidacionSnapshot;

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
        $this->id = Uuid::uuid4();
        $this->createdAt = new \DateTimeImmutable();
        $this->estadoLinea = EstadoLineaInscripcionEnum::PENDIENTE;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getInscripcion(): Inscripcion
    {
        return $this->inscripcion;
    }

    public function setInscripcion(Inscripcion $inscripcion): static
    {
        $this->inscripcion = $inscripcion;
        return $this;
    }

    public function getPersona(): PersonaFamiliar
    {
        return $this->persona;
    }

    public function setPersona(PersonaFamiliar $persona): static
    {
        $this->persona = $persona;
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

    public function setNombrePersonaSnapshot(string $nombrePersonaSnapshot): static
    {
        $this->nombrePersonaSnapshot = $nombrePersonaSnapshot;
        return $this;
    }

    public function getTipoPersonaSnapshot(): string
    {
        return $this->tipoPersonaSnapshot;
    }

    public function setTipoPersonaSnapshot(string $tipoPersonaSnapshot): static
    {
        $this->tipoPersonaSnapshot = $tipoPersonaSnapshot;
        return $this;
    }

    public function getTipoRelacionEconomicaSnapshot(): string
    {
        return $this->tipoRelacionEconomicaSnapshot;
    }

    public function setTipoRelacionEconomicaSnapshot(string $tipoRelacionEconomicaSnapshot): static
    {
        $this->tipoRelacionEconomicaSnapshot = $tipoRelacionEconomicaSnapshot;
        return $this;
    }

    public function getEstadoValidacionSnapshot(): string
    {
        return $this->estadoValidacionSnapshot;
    }

    public function setEstadoValidacionSnapshot(string $estadoValidacionSnapshot): static
    {
        $this->estadoValidacionSnapshot = $estadoValidacionSnapshot;
        return $this;
    }

    public function getNombreMenuSnapshot(): string
    {
        return $this->nombreMenuSnapshot;
    }

    public function setNombreMenuSnapshot(string $nombreMenuSnapshot): static
    {
        $this->nombreMenuSnapshot = $nombreMenuSnapshot;
        return $this;
    }

    public function getFranjaComidaSnapshot(): string
    {
        return $this->franjaComidaSnapshot;
    }

    public function setFranjaComidaSnapshot(string $franjaComidaSnapshot): static
    {
        $this->franjaComidaSnapshot = $franjaComidaSnapshot;
        return $this;
    }

    public function isEsDePagoSnapshot(): bool
    {
        return $this->esDePagoSnapshot;
    }

    public function setEsDePagoSnapshot(bool $esDePagoSnapshot): static
    {
        $this->esDePagoSnapshot = $esDePagoSnapshot;
        return $this;
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

    /**
     * Creates snapshot values from the current state of persona and menu.
     * This should be called right before persisting the line.
     */
    public function crearSnapshot(): void
    {
        $this->nombrePersonaSnapshot = $this->persona->getNombreCompleto();
        $this->tipoPersonaSnapshot = $this->persona->getTipoPersona()->value;
        $this->tipoRelacionEconomicaSnapshot = $this->persona->getTipoRelacionEconomica()->value;
        $this->estadoValidacionSnapshot = $this->persona->getEstadoValidacion()->value;
        $this->nombreMenuSnapshot = $this->menu->getNombre();
        $this->franjaComidaSnapshot = $this->menu->getFranjaComida()->value;
        $this->esDePagoSnapshot = $this->menu->isEsDePago();
    }
}
