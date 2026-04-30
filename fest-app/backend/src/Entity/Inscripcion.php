<?php

namespace App\Entity;

use App\Repository\InscripcionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Enum\EstadoInscripcionEnum;
use App\Enum\EstadoPagoEnum;
use App\Enum\MetodoPagoEnum;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;

#[ORM\Entity(repositoryClass: InscripcionRepository::class)]
#[ORM\Table(name: 'inscripcion')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('INSCRIPCION_VIEW', object)"),
        new GetCollection(
            normalizationContext: ['groups' => ['inscripcion:collection']],
            security: "is_granted('ROLE_USER')"
            ),
        new Patch(security: "is_granted('INSCRIPCION_EDIT', object) and object.getEvento().estaInscripcionAbierta()"),
    ],
    normalizationContext: ['groups' => ['inscripcion:read']],
    denormalizationContext: ['groups' => ['inscripcion:write']]
)]

#[ApiFilter(SearchFilter::class, properties: [
    'usuario' => 'exact',
    'usuario.id' => 'exact',
    'evento' => 'exact',
    'evento.id' => 'exact',
    'entidad' => 'exact',
    'entidad.id' => 'exact',
    'estadoInscripcion' => 'exact',
    'estadoPago' => 'exact',
    'metodoPago' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['createdAt', 'updatedAt', 'fechaPago'])]
#[ApiFilter(
    OrderFilter::class,
    properties: ['createdAt', 'updatedAt', 'importeTotal', 'importePagado'],
    arguments: ['orderParameterName' => 'order']
)]

class Inscripcion
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['inscripcion:read', 'inscripcion:collection'])]
    private ?string $id = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $codigo;

    #[ORM\ManyToOne(targetEntity: Entidad::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['inscripcion:read'])]
    private Entidad $entidad;

    #[ORM\ManyToOne(targetEntity: Evento::class, inversedBy: 'inscripciones')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['inscripcion:read', 'inscripcion:write', 'inscripcion:collection'])]
    #[Assert\NotNull]
    private Evento $evento;

    #[ORM\ManyToOne(targetEntity: Usuario::class, inversedBy: 'inscripciones')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['inscripcion:read', 'inscripcion:write'])]
    #[Assert\NotNull]
    private Usuario $usuario;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: EstadoInscripcionEnum::class)]
    #[Groups(['inscripcion:read', 'inscripcion:write', 'inscripcion:collection'])]
    private EstadoInscripcionEnum $estadoInscripcion;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: EstadoPagoEnum::class)]
    #[Groups(['inscripcion:read', 'inscripcion:write', 'inscripcion:collection'])]
    private EstadoPagoEnum $estadoPago;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    #[Groups(['inscripcion:read', 'inscripcion:collection'])]
    private string $importeTotal = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    #[Groups(['inscripcion:read'])]
    private string $importePagado = '0.00';

    #[ORM\Column(type: Types::STRING, length: 3)]
    #[Groups(['inscripcion:read', 'inscripcion:collection'])]
    private string $moneda = 'EUR';

    #[ORM\Column(type: Types::STRING, length: 50, enumType: MetodoPagoEnum::class, nullable: true)]
    #[Groups(['inscripcion:read'])]
    private ?MetodoPagoEnum $metodoPago = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['inscripcion:read'])]
    private ?string $referenciaPago = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['inscripcion:read'])]
    private ?\DateTimeImmutable $fechaPago = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['inscripcion:read', 'inscripcion:write'])]
    private ?string $observaciones = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['inscripcion:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['inscripcion:read'])]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, InscripcionLinea> */
    #[ORM\OneToMany(targetEntity: InscripcionLinea::class, mappedBy: 'inscripcion', cascade: ['persist', 'remove'])]
    #[Groups(['inscripcion:read'])]
    private Collection $lineas;

    /** @var Collection<int, Pago> */
    #[ORM\OneToMany(targetEntity: Pago::class, mappedBy: 'inscripcion')]
    private Collection $pagos;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->lineas = new ArrayCollection();
        $this->pagos = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->estadoInscripcion = EstadoInscripcionEnum::PENDIENTE;
        $this->estadoPago = EstadoPagoEnum::PENDIENTE;
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

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function setCodigo(string $codigo): static
    {
        $this->codigo = $codigo;
        return $this;
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

    public function getEvento(): Evento
    {
        return $this->evento;
    }

    public function setEvento(Evento $evento): static
    {
        $this->evento = $evento;
        return $this;
    }

    public function getUsuario(): Usuario
    {
        return $this->usuario;
    }

    public function setUsuario(Usuario $usuario): static
    {
        $this->usuario = $usuario;
        return $this;
    }

    public function getEstadoInscripcion(): EstadoInscripcionEnum
    {
        return $this->estadoInscripcion;
    }

    public function setEstadoInscripcion(EstadoInscripcionEnum $estadoInscripcion): static
    {
        $this->estadoInscripcion = $estadoInscripcion;
        return $this;
    }

    public function getEstadoPago(): EstadoPagoEnum
    {
        return $this->estadoPago;
    }

    public function setEstadoPago(EstadoPagoEnum $estadoPago): static
    {
        $this->estadoPago = $estadoPago;
        return $this;
    }

    public function getImporteTotal(): float
    {
        return (float) $this->importeTotal;
    }

    public function setImporteTotal(float $importeTotal): static
    {
        $this->importeTotal = (string) $importeTotal;
        return $this;
    }

    public function getImportePagado(): float
    {
        return (float) $this->importePagado;
    }

    public function setImportePagado(float $importePagado): static
    {
        $this->importePagado = (string) $importePagado;
        return $this;
    }

    public function getMoneda(): string
    {
        return $this->moneda;
    }

    public function setMoneda(string $moneda): static
    {
        $this->moneda = $moneda;
        return $this;
    }

    public function getMetodoPago(): ?MetodoPagoEnum
    {
        return $this->metodoPago;
    }

    public function setMetodoPago(?MetodoPagoEnum $metodoPago): static
    {
        $this->metodoPago = $metodoPago;
        return $this;
    }

    public function getReferenciaPago(): ?string
    {
        return $this->referenciaPago;
    }

    public function setReferenciaPago(?string $referenciaPago): static
    {
        $this->referenciaPago = $referenciaPago;
        return $this;
    }

    public function getFechaPago(): ?\DateTimeImmutable
    {
        return $this->fechaPago;
    }

    public function setFechaPago(?\DateTimeImmutable $fechaPago): static
    {
        $this->fechaPago = $fechaPago;
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

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, InscripcionLinea> */
    public function getLineas(): Collection
    {
        return $this->lineas;
    }

    public function addLinea(InscripcionLinea $linea): static
    {
        if (!$this->lineas->contains($linea)) {
            $this->lineas->add($linea);
            $linea->setInscripcion($this);
        }
        return $this;
    }

    public function removeLinea(InscripcionLinea $linea): static
    {
        $this->lineas->removeElement($linea);
        return $this;
    }

    #[Groups(['inscripcion:collection'])]
    public function getTotalLineas(): int
    {
        return $this->lineas->count();
    }

    /** @return Collection<int, Pago> */
    public function getPagos(): Collection
    {
        return $this->pagos;
    }

    public function calcularImporteTotal(): float
    {
        $total = 0.0;
        foreach ($this->lineas as $linea) {
            if ($linea->getEstadoLinea() !== \App\Enum\EstadoLineaInscripcionEnum::CANCELADA) {
                $total += $linea->getPrecioUnitario();
            }
        }
        return $total;
    }

    public function actualizarEstadoPago(): void
    {
        $total = $this->calcularImporteTotal();

        if ($total === 0.0) {
            $this->estadoPago = EstadoPagoEnum::NO_REQUIERE_PAGO;
        } elseif ($this->importePagado >= $total) {
            $this->estadoPago = EstadoPagoEnum::PAGADO;
        } elseif ($this->importePagado > 0) {
            $this->estadoPago = EstadoPagoEnum::PARCIAL;
        } else {
            $this->estadoPago = EstadoPagoEnum::PENDIENTE;
        }
    }
}
