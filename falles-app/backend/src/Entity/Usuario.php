<?php

namespace App\Entity;

use App\Repository\UsuarioRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Dto\PersonaFamiliarView;
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use App\Enum\TipoRelacionEconomicaEnum;
use App\Enum\CensadoViaEnum;
use App\Enum\EstadoValidacionEnum;
use App\State\PersonaFamiliarMiasProvider;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UsuarioRepository::class)]
#[ORM\Table(name: 'usuario')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => ['usuario:read']],
    denormalizationContext: ['groups' => ['usuario:write']],
    operations: [
        new Get(security: "is_granted('VIEW', object)"),
        new GetCollection(security: "is_granted('ROLE_ADMIN_ENTIDAD')"),
        new GetCollection(
            uriTemplate: '/persona_familiares/mias',
            provider: PersonaFamiliarMiasProvider::class,
            output: PersonaFamiliarView::class,
            normalizationContext: ['groups' => ['persona_familiar_mia:read']],
            security: "is_granted('ROLE_USER')"
        ),
        new Patch(security: "is_granted('EDIT', object)"),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'entidad' => 'exact',
    'entidad.id' => 'exact',
    'estadoValidacion' => 'exact',
    'tipoUsuarioEconomico' => 'exact',
    'nombre' => 'partial',
    'apellidos' => 'partial',
    'nombreCompleto' => 'partial',
    'email' => 'partial',
])]
#[ApiFilter(BooleanFilter::class, properties: ['activo', 'esCensadoInterno'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt', 'fechaSolicitudAlta', 'fechaAltaCenso', 'fechaValidacion'])]
#[ApiFilter(
    OrderFilter::class,
    properties: ['nombreCompleto', 'createdAt', 'fechaSolicitudAlta', 'fechaValidacion'],
    arguments: ['orderParameterName' => 'order']
)]
class Usuario implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['usuario:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Entidad::class, inversedBy: 'usuarios')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['usuario:read', 'usuario:write'])]
    private Entidad $entidad;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Groups(['usuario:read', 'usuario:write'])]
    #[Assert\NotBlank]
    private string $nombre;

    #[ORM\Column(type: Types::STRING, length: 150)]
    #[Groups(['usuario:read', 'usuario:write'])]
    #[Assert\NotBlank]
    private string $apellidos;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['usuario:read', 'relacion:read'])]
    private string $nombreCompleto;

    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    #[Groups(['usuario:read', 'usuario:write'])]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['usuario:read', 'usuario:write'])]
    private ?string $telefono = null;

    #[ORM\Column(type: Types::STRING)]
    private string $password;

    /** @var string[] */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['usuario:read'])]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['usuario:read', 'usuario:write'])]
    private bool $activo = true;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: TipoRelacionEconomicaEnum::class)]
    #[Groups(['usuario:read', 'usuario:write'])]
    private TipoRelacionEconomicaEnum $tipoUsuarioEconomico;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: EstadoValidacionEnum::class)]
    #[Groups(['usuario:read', 'usuario:write'])]
    private EstadoValidacionEnum $estadoValidacion;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['usuario:read'])]
    private bool $esCensadoInterno = false;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['usuario:read'])]
    private ?string $codigoRegistroUsado = null;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: CensadoViaEnum::class, nullable: true)]
    #[Groups(['usuario:read'])]
    private ?CensadoViaEnum $censadoVia = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?self $censoEntradaRef = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['usuario:read', 'usuario:write'])]
    private ?\DateTimeImmutable $fechaSolicitudAlta = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['usuario:read', 'usuario:write'])]
    private ?\DateTimeImmutable $fechaAltaCenso = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['usuario:read'])]
    private ?\DateTimeImmutable $fechaBajaCenso = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['usuario:write'])]
    private ?string $motivoBajaCenso = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['usuario:read'])]
    private ?self $validadoPor = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['usuario:read'])]
    private ?\DateTimeImmutable $fechaValidacion = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['usuario:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['usuario:read'])]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Inscripcion> */
    #[ORM\OneToMany(targetEntity: Inscripcion::class, mappedBy: 'usuario')]
    private Collection $inscripciones;

    /** @var Collection<int, Entidad> */
    #[ORM\ManyToMany(targetEntity: Entidad::class, mappedBy: 'admins')]
    private Collection $entidadesAdmin;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['usuario:read', 'usuario:write'])]
    private ?\DateTimeImmutable $fechaNacimiento = null;

    /** @var Collection<int, RelacionUsuario> */
    #[ORM\OneToMany(targetEntity: RelacionUsuario::class, mappedBy: 'usuarioOrigen', cascade: ['persist', 'remove'])]
    private Collection $relacionesOrigen;

    /** @var Collection<int, RelacionUsuario> */
    #[ORM\OneToMany(targetEntity: RelacionUsuario::class, mappedBy: 'usuarioDestino', cascade: ['persist', 'remove'])]
    private Collection $relacionesDestino;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->familiares = new ArrayCollection();
        $this->inscripciones = new ArrayCollection();
        $this->entidadesAdmin = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->estadoValidacion = EstadoValidacionEnum::PENDIENTE_VALIDACION;
        $this->tipoUsuarioEconomico = TipoRelacionEconomicaEnum::INTERNO;
        $this->relacionesOrigen  = new ArrayCollection();
        $this->relacionesDestino = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function syncNombreCompleto(): void
    {
        $this->nombreCompleto = trim($this->nombre . ' ' . $this->apellidos);
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

    public function getApellidos(): string
    {
        return $this->apellidos;
    }

    public function setApellidos(string $apellidos): static
    {
        $this->apellidos = $apellidos;
        return $this;
    }

    public function getNombreCompleto(): string { 
        return $this->nombreCompleto; 
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getTelefono(): ?string
    {
        return $this->telefono;
    }

    public function setTelefono(?string $telefono): static
    {
        $this->telefono = $telefono;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getRoles(): array
    {
        $roles = array_filter(
            $this->roles,
            static fn (mixed $role): bool => is_string($role) && $role !== ''
        );

        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
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

    public function getTipoUsuarioEconomico(): TipoRelacionEconomicaEnum
    {
        return $this->tipoUsuarioEconomico;
    }

    public function setTipoUsuarioEconomico(TipoRelacionEconomicaEnum $tipoUsuarioEconomico): static
    {
        $this->tipoUsuarioEconomico = $tipoUsuarioEconomico;
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

    public function puedeAcceder(): bool
    {
        return $this->estadoValidacion->puedeAcceder() && $this->activo;
    }

    public function isEsCensadoInterno(): bool
    {
        return $this->esCensadoInterno;
    }

    public function setEsCensadoInterno(bool $esCensadoInterno): static
    {
        $this->esCensadoInterno = $esCensadoInterno;
        return $this;
    }

    public function getCodigoRegistroUsado(): ?string
    {
        return $this->codigoRegistroUsado;
    }

    public function setCodigoRegistroUsado(?string $codigoRegistroUsado): static
    {
        $this->codigoRegistroUsado = $codigoRegistroUsado;
        return $this;
    }

    public function getCensadoVia(): ?CensadoViaEnum
    {
        return $this->censadoVia;
    }

    public function setCensadoVia(?CensadoViaEnum $censadoVia): static
    {
        $this->censadoVia = $censadoVia;
        return $this;
    }

    public function getCensoEntradaRef(): ?self
    {
        return $this->censoEntradaRef;
    }

    public function setCensoEntradaRef(?self $censoEntradaRef): static
    {
        $this->censoEntradaRef = $censoEntradaRef;
        return $this;
    }

    public function getFechaSolicitudAlta(): ?\DateTimeImmutable
    {
        return $this->fechaSolicitudAlta;
    }

    public function setFechaSolicitudAlta(?\DateTimeImmutable $fechaSolicitudAlta): static
    {
        $this->fechaSolicitudAlta = $fechaSolicitudAlta;
        return $this;
    }

    public function getFechaAltaCenso(): ?\DateTimeImmutable
    {
        return $this->fechaAltaCenso;
    }

    public function setFechaAltaCenso(?\DateTimeImmutable $fechaAltaCenso): static
    {
        $this->fechaAltaCenso = $fechaAltaCenso;
        return $this;
    }

    public function getFechaBajaCenso(): ?\DateTimeImmutable
    {
        return $this->fechaBajaCenso;
    }

    public function setFechaBajaCenso(?\DateTimeImmutable $fechaBajaCenso): static
    {
        $this->fechaBajaCenso = $fechaBajaCenso;
        return $this;
    }

    public function getMotivoBajaCenso(): ?string
    {
        return $this->motivoBajaCenso;
    }

    public function setMotivoBajaCenso(?string $motivoBajaCenso): static
    {
        $this->motivoBajaCenso = $motivoBajaCenso;
        return $this;
    }

    public function getValidadoPor(): ?self
    {
        return $this->validadoPor;
    }

    public function setValidadoPor(?self $validadoPor): static
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

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** @return Collection<int, PersonaFamiliar> */
    public function getFamiliares(): Collection
    {
        return $this->familiares;
    }

    /** @return Collection<int, Inscripcion> */
    public function getInscripciones(): Collection
    {
        return $this->inscripciones;
    }

    /** @return Collection<int, Entidad> */
    public function getEntidadesAdmin(): Collection
    {
        return $this->entidadesAdmin;
    }

    // UserInterface methods
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function eraseCredentials(): void
    {
        // No sensitive data to clear
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

    /** @return Collection<int, RelacionUsuario> */
    public function getRelacionesOrigen(): Collection
    {
        return $this->relacionesOrigen;
    }

    public function addRelacionOrigen(RelacionUsuario $relacion): static
    {
        if (!$this->relacionesOrigen->contains($relacion)) {
            $this->relacionesOrigen->add($relacion);
            $relacion->setUsuarioOrigen($this);
        }
        return $this;
    }

    public function removeRelacionOrigen(RelacionUsuario $relacion): static
    {
        $this->relacionesOrigen->removeElement($relacion);
        return $this;
    }

    // --- Relaciones Destino ---

    /** @return Collection<int, RelacionUsuario> */
    public function getRelacionesDestino(): Collection
    {
        return $this->relacionesDestino;
    }

    public function addRelacionDestino(RelacionUsuario $relacion): static
    {
        if (!$this->relacionesDestino->contains($relacion)) {
            $this->relacionesDestino->add($relacion);
            $relacion->setUsuarioDestino($this);
        }
        return $this;
    }

    public function removeRelacionDestino(RelacionUsuario $relacion): static
    {
        $this->relacionesDestino->removeElement($relacion);
        return $this;
    }

    // --- Helper: todos los relacionados ---

    /** @return array<RelacionUsuario> */
    public function getRelacionados(): array
    {
        return array_merge(
            $this->relacionesOrigen->toArray(),
            $this->relacionesDestino->toArray()
        );
    }
}
