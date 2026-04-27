<?php

namespace App\Entity;

use App\Repository\EventoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\ApiProperty;
use App\Dto\InvitadoView;
use App\Enum\TipoEventoEnum;
use App\Enum\EstadoEventoEnum;
use App\State\EventoInvitadosProvider;
use App\State\EventoReporteParticipantesProvider;
use App\State\EventoWriteProcessor;
use App\State\EventoCancelProcessor;
use App\State\EventoForceDeleteProcessor;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;

#[ORM\Entity(repositoryClass: EventoRepository::class)]
#[ORM\Table(name: 'evento')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/eventos/{id}',
            normalizationContext: [
                'groups' => ['evento:item:min', 'actividad-evento:evento:item:min'],
                'enable_max_depth' => 1,
            ],
            security: "is_granted('EVENTO_VIEW', object)"
        ),
        new GetCollection(
            uriTemplate: '/eventos',
            normalizationContext: ['groups' => ['evento:collection'], 'enable_max_depth' => 1],
            security: "is_granted('ROLE_USER')"
        ),
        new GetCollection(
            uriTemplate: '/eventos/{id}/invitados',
            uriVariables: [
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            normalizationContext: ['groups' => ['invitado:read']],
            security: "is_granted('ROLE_USER')",
            output: InvitadoView::class,
            provider: EventoInvitadosProvider::class
        ),
        new GetCollection(
            uriTemplate: '/eventos/{id}/participantes_externos',
            uriVariables: [
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            normalizationContext: ['groups' => ['invitado:read']],
            security: "is_granted('ROLE_USER')",
            output: InvitadoView::class,
            provider: EventoInvitadosProvider::class
        ),
        new Post(security: "is_granted('ROLE_ADMIN_ENTIDAD')", processor: EventoWriteProcessor::class),
        new Patch(security: "is_granted('EVENTO_EDIT', object)", processor: EventoWriteProcessor::class),
        new Delete(security: "is_granted('ROLE_ADMIN_ENTIDAD') or is_granted('ROLE_SUPERADMIN')"),
        new Post(
            uriTemplate: '/eventos/{id}/cancelar',
            security: "is_granted('EVENTO_EDIT', object)",
            processor: EventoCancelProcessor::class
        ),
        new Post(
            uriTemplate: '/eventos/{id}/force_delete',
            security: "is_granted('ROLE_ADMIN_ENTIDAD') or is_granted('ROLE_SUPERADMIN')",
            processor: EventoForceDeleteProcessor::class
        ),
        new Get(
            uriTemplate: '/eventos/{id}/reporte-participantes',
            formats: ['pdf' => ['application/pdf']],
            uriVariables: [
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            security: "is_granted('ROLE_ADMIN_ENTIDAD')",
            output: false,
            provider: EventoReporteParticipantesProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['evento:read'], 'enable_max_depth' => 1],
    denormalizationContext: ['groups' => ['evento:write']],
    order: ['fechaEvento' => 'ASC'],
    paginationClientEnabled: true,
    paginationEnabled: true,
)]
#[ApiFilter(DateFilter::class, properties: ['fechaEvento' => DateFilter::EXCLUDE_NULL])]
#[ApiFilter(SearchFilter::class, properties: [
    'estado' => 'exact',
    'visible' => 'exact',
    'entidad' => 'exact',
    'titulo' => 'partial',
])]
#[ApiFilter(
    OrderFilter::class, properties: ['fechaEvento', 'horaInicio', 'createdAt'],
    arguments: ['orderParameterName' => 'order']
)]
class Evento
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['evento:read', 'evento:collection', 'inscripcion:read', 'inscripcion:collection', 'evento:item:min'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Entidad::class, inversedBy: 'eventos')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['evento:read', 'evento:write', 'evento:read:item'])]
    #[MaxDepth(1)]
    private Entidad $entidad;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups([
        'evento:read',
        'evento:write',
        'evento:collection',
        'inscripcion:read',
        'inscripcion:collection',
        'evento:item:min',
    ])]
    private string $titulo;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['evento:read', 'evento:write'])]
    private string $slug;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups([
        'evento:read',
        'evento:write',
        'evento:collection',
        'inscripcion:read',
        'inscripcion:collection',
        'evento:item:min',
    ])]
    private ?string $descripcion = null;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: TipoEventoEnum::class)]
    #[Groups(['evento:read', 'evento:write', 'evento:item:min'])]
    #[Assert\NotNull]
    private TipoEventoEnum $tipoEvento;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups([
        'evento:read',
        'evento:write',
        'evento:collection',
        'inscripcion:read',
        'inscripcion:collection',
        'evento:item:min',
    ])]
    #[Assert\NotNull]
    private \DateTimeImmutable $fechaEvento;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    #[Groups([
        'evento:read',
        'evento:write',
        'evento:collection',
        'inscripcion:read',
        'inscripcion:collection',
        'evento:item:min',
    ])]
    private ?\DateTimeImmutable $horaInicio = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    #[Groups(['evento:read', 'evento:write', 'evento:item:min'])]
    private ?\DateTimeImmutable $horaFin = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups([
        'evento:read',
        'evento:write',
        'evento:collection',
        'inscripcion:read',
        'inscripcion:collection',
        'evento:item:min',
    ])]
    private ?string $lugar = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['evento:read', 'evento:write'])]
    private ?int $aforo = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['evento:read', 'evento:write', 'evento:collection', 'evento:read:item', 'inscripcion:read', 'evento:item:min'])]
    private ?\DateTimeImmutable $fechaInicioInscripcion = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['evento:read', 'evento:write', 'evento:collection', 'evento:read:item', 'inscripcion:read', 'evento:item:min'])]
    private ?\DateTimeImmutable $fechaFinInscripcion = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['evento:read', 'evento:write'])]
    private bool $visible = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['evento:read', 'evento:write', 'evento:item:min'])]
    private bool $admitePago = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['evento:read', 'evento:write', 'evento:item:min'])]
    private bool $permiteInvitados = true;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: EstadoEventoEnum::class)]
    #[Groups(['evento:read', 'evento:write', 'evento:collection', 'inscripcion:collection'])]
    private EstadoEventoEnum $estado;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['evento:read', 'evento:write'])]
    private bool $requiereVerificacionAcceso = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['evento:read', 'evento:write'])]
    private ?\DateTimeImmutable $ventanaInicioVerificacion = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['evento:read', 'evento:write'])]
    private ?\DateTimeImmutable $ventanaFinVerificacion = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['evento:read'])]
    private ?string $imagenVerificacion = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['evento:read'])]
    private ?string $codigoVisual = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, ActividadEvento> */
    #[ORM\OneToMany(targetEntity: ActividadEvento::class, mappedBy: 'evento', cascade: [
        'persist',
        'remove',
    ], fetch: 'EXTRA_LAZY')]
    #[ApiProperty(writableLink: true)]
    /** @return Collection<int, ActividadEvento> */
    #[Groups(['evento:read', 'actividad-evento:read', 'evento:item:min'])]
    #[MaxDepth(2)]
    private Collection $actividades;

    /** @var Collection<int, Inscripcion> */
    #[ORM\OneToMany(targetEntity: Inscripcion::class, mappedBy: 'evento', fetch: 'EXTRA_LAZY')]
    private Collection $inscripciones;

    #[ApiProperty(readable: true, writable: false)]
    #[Groups([
        'evento:read',
        'evento:collection',
        'evento:read:item',
        'inscripcion:read',
        'inscripcion:collection',
        'evento:item:min',
    ])]
    private ?bool $inscripcionAbierta = null;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->actividades = new ArrayCollection();
        $this->inscripciones = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->estado = EstadoEventoEnum::BORRADOR;
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

    public function getEntidad(): Entidad
    {
        return $this->entidad;
    }

    public function setEntidad(Entidad $entidad): static
    {
        $this->entidad = $entidad;

        return $this;
    }

    public function getTitulo(): string
    {
        return $this->titulo;
    }

    public function setTitulo(string $titulo): static
    {
        $this->titulo = $titulo;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

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

    public function getTipoEvento(): TipoEventoEnum
    {
        return $this->tipoEvento;
    }

    public function setTipoEvento(TipoEventoEnum $tipoEvento): static
    {
        $this->tipoEvento = $tipoEvento;

        return $this;
    }

    public function getFechaEvento(): \DateTimeImmutable
    {
        return $this->fechaEvento;
    }

    public function setFechaEvento(\DateTimeImmutable $fechaEvento): static
    {
        $this->fechaEvento = $fechaEvento;

        return $this;
    }

    public function getHoraInicio(): ?\DateTimeImmutable
    {
        return $this->horaInicio;
    }

    public function setHoraInicio(?\DateTimeImmutable $horaInicio): static
    {
        $this->horaInicio = $horaInicio;

        return $this;
    }

    public function getHoraFin(): ?\DateTimeImmutable
    {
        return $this->horaFin;
    }

    public function setHoraFin(?\DateTimeImmutable $horaFin): static
    {
        $this->horaFin = $horaFin;

        return $this;
    }

    public function getLugar(): ?string
    {
        return $this->lugar;
    }

    public function setLugar(?string $lugar): static
    {
        $this->lugar = $lugar;

        return $this;
    }

    public function getAforo(): ?int
    {
        return $this->aforo;
    }

    public function setAforo(?int $aforo): static
    {
        $this->aforo = $aforo;

        return $this;
    }

    public function getFechaInicioInscripcion(): ?\DateTimeImmutable
    {
        return $this->fechaInicioInscripcion;
    }

    public function setFechaInicioInscripcion(?\DateTimeImmutable $fechaInicioInscripcion): static
    {
        $this->fechaInicioInscripcion = $fechaInicioInscripcion;

        return $this;
    }

    #[SerializedName('fechaLimiteInscripcion')]
    public function getFechaFinInscripcion(): ?\DateTimeImmutable
    {
        return $this->fechaFinInscripcion;
    }

    public function setFechaFinInscripcion(?\DateTimeImmutable $fechaFinInscripcion): static
    {
        $this->fechaFinInscripcion = $fechaFinInscripcion;

        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    public function isAdmitePago(): bool
    {
        return $this->admitePago;
    }

    public function setAdmitePago(bool $admitePago): static
    {
        $this->admitePago = $admitePago;

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

    public function getEstado(): EstadoEventoEnum
    {
        return $this->estado;
    }

    public function setEstado(EstadoEventoEnum $estado): static
    {
        $this->estado = $estado;

        return $this;
    }

    public function isRequiereVerificacionAcceso(): bool
    {
        return $this->requiereVerificacionAcceso;
    }

    public function setRequiereVerificacionAcceso(bool $requiereVerificacionAcceso): static
    {
        $this->requiereVerificacionAcceso = $requiereVerificacionAcceso;

        return $this;
    }

    public function getVentanaInicioVerificacion(): ?\DateTimeImmutable
    {
        return $this->ventanaInicioVerificacion;
    }

    public function setVentanaInicioVerificacion(?\DateTimeImmutable $ventanaInicioVerificacion): static
    {
        $this->ventanaInicioVerificacion = $ventanaInicioVerificacion;

        return $this;
    }

    public function getVentanaFinVerificacion(): ?\DateTimeImmutable
    {
        return $this->ventanaFinVerificacion;
    }

    public function setVentanaFinVerificacion(?\DateTimeImmutable $ventanaFinVerificacion): static
    {
        $this->ventanaFinVerificacion = $ventanaFinVerificacion;

        return $this;
    }

    public function getImagenVerificacion(): ?string
    {
        return $this->imagenVerificacion;
    }

    public function setImagenVerificacion(?string $imagenVerificacion): static
    {
        $this->imagenVerificacion = $imagenVerificacion;

        return $this;
    }

    public function getCodigoVisual(): ?string
    {
        return $this->codigoVisual;
    }

    public function setCodigoVisual(?string $codigoVisual): static
    {
        $this->codigoVisual = $codigoVisual;

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

    public function getActividades(): Collection
    {
        return $this->actividades;
    }

    /**
     * @param iterable<ActividadEvento> $actividades
     */
    public function setActividades(iterable $actividades): static
    {
        foreach ($this->actividades as $actividad) {
            $this->actividades->removeElement($actividad);
        }

        foreach ($actividades as $actividad) {
            if ($actividad instanceof ActividadEvento) {
                $this->addActividad($actividad);
            }
        }

        return $this;
    }

    public function addActividad(ActividadEvento $actividad): static
    {
        if (!$this->actividades->contains($actividad)) {
            $this->actividades->add($actividad);
            $actividad->setEvento($this);
        }

        return $this;
    }

    public function removeActividad(ActividadEvento $actividad): static
    {
        $this->actividades->removeElement($actividad);

        return $this;
    }

    /** @return Collection<int, Inscripcion> */
    public function getInscripciones(): Collection
    {
        return $this->inscripciones;
    }

    #[Groups(['evento:read', 'evento:collection', 'evento:read:item'])]
    public function getPersonasApuntadas(): int
    {
        $total = 0;

        foreach ($this->inscripciones as $inscripcion) {
            $total += $inscripcion->getLineas()->count();
        }

        return $total;
    }

    public function getInscripcionAbierta(): bool
    {
        return $this->isInscripcionAbierta();
    }

    public function isInscripcionAbierta(): bool
    {
        if (!$this->fechaInicioInscripcion || !$this->fechaFinInscripcion) {
            return false;
        }

        $ahora = new \DateTimeImmutable();
        $fechaFinInclusiva = $this->fechaFinInscripcion->setTime(23, 59, 59, 999999);

        return $ahora >= $this->fechaInicioInscripcion
            && $ahora <= $fechaFinInclusiva;
    }

    public function estaInscripcionAbierta(): bool
    {
        return $this->getInscripcionAbierta()
            && $this->estado === EstadoEventoEnum::PUBLICADO;
    }

    public function tieneActividadesActivas(): bool
    {
        foreach ($this->actividades as $actividad) {
            if ($actividad->isActivo()) {
                return true;
            }
        }

        return false;
    }

    public function permiteGestionInvitadosConActividades(): bool
    {
        return $this->permiteInvitados && $this->tieneActividadesActivas();
    }

    public function permiteGestionInvitados(): bool
    {
        return $this->permiteGestionInvitadosConActividades();
    }
}
