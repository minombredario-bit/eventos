<?php

namespace App\Entity;

use ApiPlatform\Metadata\Post;
use App\Dto\AdminCreateUsuarioInput;
use App\Dto\AdminUpdateUsuarioInput;
use App\Enum\TipoPersonaEnum;
use App\Repository\UsuarioRepository;
use App\State\AdminCreateUsuarioProcessor;
use App\State\AdminUpdateUsuarioProcessor;
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
use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use App\Enum\TipoRelacionEconomicaEnum;
use App\Enum\CensadoViaEnum;
use App\Enum\EstadoValidacionEnum;
use App\Enum\MetodoPagoEnum;
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
    operations: [
        new Get(security: "is_granted('VIEW', object)"),
        // Admin-specific GET that returns fields needed by the admin frontend
        new Get(
            uriTemplate: '/admin/usuarios/{id}',
            normalizationContext: ['groups' => ['usuario:read', 'read_user_admin']],
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_ADMIN_ENTIDAD') or is_granted('ROLE_SUPERADMIN')",
        ),
        new GetCollection(
            normalizationContext: ['groups' => ['usuario:collection']],
            security: "is_granted('ROLE_ADMIN_ENTIDAD') or is_granted('ROLE_SUPERADMIN')",
        ),
        new GetCollection(
            uriTemplate: '/persona_familiares/mias',
            normalizationContext: ['groups' => ['persona_familiar_mia:read']],
            security: "is_granted('ROLE_USER')",
            output: PersonaFamiliarView::class,
            provider: PersonaFamiliarMiasProvider::class
        ),
        new Post(
            uriTemplate: '/admin/usuarios',
            normalizationContext: ['groups' => ['usuario:read', 'read_user_admin']],
            denormalizationContext: ['groups' => ['admin_usuario_create']],
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_ADMIN_ENTIDAD') or is_granted('ROLE_SUPERADMIN')",
            input: AdminCreateUsuarioInput::class,
            processor: AdminCreateUsuarioProcessor::class,
        ),
        new Patch(security: "is_granted('EDIT', object)"),
        // Admin-specific PATCH that routes through a processor to handle relaciones and cargos
        new Patch(
            uriTemplate: '/admin/usuarios/{id}',
            normalizationContext: ['groups' => ['usuario:read', 'read_user_admin']],
            denormalizationContext: ['groups' => ['admin_usuario_update']],
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_ADMIN_ENTIDAD') or is_granted('ROLE_SUPERADMIN')",
            input: AdminUpdateUsuarioInput::class,
            processor: AdminUpdateUsuarioProcessor::class
        ),
    ],
    normalizationContext: ['groups' => ['usuario:read']],
    denormalizationContext: ['groups' => ['usuario:write']]
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
#[ApiFilter(DateFilter::class, properties: ['createdAt', 'fechaSolicitudAlta', 'fechaAltaCenso','fechaBajaCenso', 'fechaValidacion'])]
#[ApiFilter(ExistsFilter::class, properties: ['fechaAltaCenso', 'fechaBajaCenso'])]
#[ApiFilter(
    OrderFilter::class,
    properties: ['nombreCompleto', 'createdAt', 'fechaSolicitudAlta', 'fechaValidacion'],
    arguments: ['orderParameterName' => 'order']
)]
class Usuario implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['usuario:read', 'usuario:list', 'usuario:collection', 'relacion:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Entidad::class, inversedBy: 'usuarios')]
    #[ORM\JoinColumn(nullable: false)]
    private Entidad $entidad;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Groups(['usuario:read', 'usuario:write', 'usuario:collection'])]
    #[Assert\NotBlank]
    private string $nombre;

    #[ORM\Column(type: Types::STRING, length: 150)]
    #[Groups(['usuario:read', 'usuario:write', 'usuario:collection'])]
    #[Assert\NotBlank]
    private string $apellidos;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['usuario:read', 'relacion:read', 'usuario:list', 'usuario:collection'])]
    private string $nombreCompleto;

    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    #[Groups(['usuario:read', 'usuario:write'])]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['usuario:read', 'usuario:write', 'usuario:list', 'usuario:collection'])]
    private ?string $telefono = null;

    #[ORM\Column(type: Types::STRING)]
    private string $password;

    /** @var string[] */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['usuario:read','read_user_admin'])]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['usuario:read', 'usuario:write'])]
    private bool $activo = true;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: EstadoValidacionEnum::class)]
    #[Groups(['usuario:read', 'usuario:write', 'read_user_admin'])]
    private EstadoValidacionEnum $estadoValidacion = EstadoValidacionEnum::PENDIENTE_VALIDACION;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: TipoRelacionEconomicaEnum::class)]
    #[Groups(['usuario:read', 'usuario:write'])]
    private TipoRelacionEconomicaEnum $tipoUsuarioEconomico;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: TipoPersonaEnum::class)]
    #[Groups(['usuario:read', 'usuario:write'])]
    #[Assert\NotNull]
    private TipoPersonaEnum $tipoPersona;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, enumType: CensadoViaEnum::class)]
    private ?CensadoViaEnum $censadoVia = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Groups(['usuario:read', 'usuario:write', 'usuario:collection'])]
    private ?int $antiguedad = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Groups(['usuario:read', 'usuario:write', 'usuario:collection'])]
    private ?int $antiguedadReal = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, enumType: MetodoPagoEnum::class)]
    #[Groups(['usuario:read', 'usuario:write'])]
    private ?MetodoPagoEnum $formaPagoPreferida = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['usuario:read'])]
    private bool $debeCambiarPassword = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $passwordActualizadaAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['usuario:read', 'usuario:write'])]
    private ?\DateTimeImmutable $fechaAltaCenso = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['usuario:read'])]
    private ?\DateTimeImmutable $fechaBajaCenso = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['usuario:write','read_user_admin'])]
    private ?string $motivoBajaCenso = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Inscripcion> */
    #[ORM\OneToMany(targetEntity: Inscripcion::class, mappedBy: 'usuario')]
    private Collection $inscripciones;

    /** @var Collection<int, Entidad> */
    #[ORM\ManyToMany(targetEntity: Entidad::class, mappedBy: 'admins')]
    private Collection $entidadesAdmin;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['usuario:read', 'usuario:write', 'read_user_admin'])]
    private ?\DateTimeImmutable $fechaNacimiento = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['usuario:read', 'read_user_admin'])]
    private bool $aceptoLopd = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['usuario:read', 'read_user_admin'])]
    private ?\DateTimeImmutable $aceptoLopdAt = null;

    /** @var Collection<int, RelacionUsuario> */
    #[ORM\OneToMany(targetEntity: RelacionUsuario::class, mappedBy: 'usuarioOrigen', cascade: ['persist', 'remove'])]
    private Collection $relacionesOrigen;

    /** @var Collection<int, RelacionUsuario> */
    #[ORM\OneToMany(targetEntity: RelacionUsuario::class, mappedBy: 'usuarioDestino', cascade: ['persist', 'remove'])]
    private Collection $relacionesDestino;

    /** @var Collection<int, UsuarioTemporadaCargo> */
    #[ORM\OneToMany(
        targetEntity: UsuarioTemporadaCargo::class,
        mappedBy: 'usuario',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $cargosTemporada;

    /** @var Collection<int, UsuarioReconocimiento> */
    #[ORM\OneToMany(targetEntity: UsuarioReconocimiento::class, mappedBy: 'usuario')]
    private Collection $usuarioReconocimientos;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->inscripciones = new ArrayCollection();
        $this->entidadesAdmin = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->tipoUsuarioEconomico = TipoRelacionEconomicaEnum::INTERNO;
        $this->relacionesOrigen  = new ArrayCollection();
        $this->relacionesDestino = new ArrayCollection();
        $this->cargosTemporada = new ArrayCollection();
        $this->usuarioReconocimientos = new ArrayCollection();
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

    /**
     * Devuelve el último reconocimiento concedido al usuario en su entidad.
     */
    #[Groups(['usuario:read', 'read_user_admin'])]
    public function getUltimoReconocimiento(): ?string
    {
        $ultimo = $this->findUltimoUsuarioReconocimiento();

        if ($ultimo === null) {
            return null;
        }

        $nombre = $ultimo->getReconocimiento()->getNombre();
        $fechaConcesion = $ultimo->getFechaConcesion();

        if ($fechaConcesion === null) {
            return $nombre;
        }

        return $nombre . ' | ' . $fechaConcesion->format('d-m-Y');
    }

    /**
     * Devuelve el próximo reconocimiento por antigüedad que todavía no ha recibido el usuario.
     * Si ya tiene todos los reconocimientos disponibles para su antigüedad actual, devuelve el siguiente futuro.
     */
    #[Groups(['usuario:read', 'read_user_admin'])]
    public function getProximoReconocimiento(): ?string
    {
        if ($this->antiguedad === null || !isset($this->entidad)) {
            return null;
        }

        $candidatos = $this->getReconocimientosAntiguedadOrdenados();

        if ($candidatos === []) {
            return null;
        }

        $obtenidos = [];

        foreach ($this->usuarioReconocimientos as $usuarioReconocimiento) {
            if ($usuarioReconocimiento->getEntidad()->getId() !== $this->entidad->getId()) {
                continue;
            }

            $reconocimientoId = $usuarioReconocimiento->getReconocimiento()->getId();

            if ($reconocimientoId !== null) {
                $obtenidos[$reconocimientoId] = true;
            }
        }

        $siguienteFuturo = null;

        foreach ($candidatos as $reconocimiento) {
            $id = $reconocimiento->getId();

            if ($id !== null && isset($obtenidos[$id])) {
                continue;
            }

            $minAntiguedad = $reconocimiento->getMinAntiguedad();

            if ($minAntiguedad === null) {
                continue;
            }

            if ((float) $this->antiguedad < $minAntiguedad) {
                return $reconocimiento->getNombre();
            }

            if ($siguienteFuturo === null) {
                $siguienteFuturo = $reconocimiento->getNombre();
            }
        }

        return $siguienteFuturo;
    }

    /**
     * @return Reconocimiento[]
     */
    private function getReconocimientosAntiguedadOrdenados(): array
    {
        if (!isset($this->entidad)) {
            return [];
        }

        $reconocimientos = [];

        foreach ($this->entidad->getReconocimientos() as $reconocimiento) {
            if (!$reconocimiento->isActivo()) {
                continue;
            }

            if ($reconocimiento->getTipo() !== Reconocimiento::TIPO_ANTIGUEDAD) {
                continue;
            }

            if ($reconocimiento->getMinAntiguedad() === null) {
                continue;
            }

            $reconocimientos[] = $reconocimiento;
        }

        usort(
            $reconocimientos,
            static function (Reconocimiento $left, Reconocimiento $right): int {
                $minLeft = $left->getMinAntiguedad() ?? 0.0;
                $minRight = $right->getMinAntiguedad() ?? 0.0;

                if ($minLeft !== $minRight) {
                    return $minLeft <=> $minRight;
                }

                if ($left->getOrden() !== $right->getOrden()) {
                    return $left->getOrden() <=> $right->getOrden();
                }

                return strcmp((string) $left->getNombre(), (string) $right->getNombre());
            }
        );

        return $reconocimientos;
    }

    private function findUltimoUsuarioReconocimiento(): ?UsuarioReconocimiento
    {
        if (!isset($this->entidad)) {
            return null;
        }

        $ultimo = null;

        foreach ($this->usuarioReconocimientos as $usuarioReconocimiento) {
            if ($usuarioReconocimiento->getEntidad()->getId() !== $this->entidad->getId()) {
                continue;
            }

            if ($ultimo === null || $this->isMoreRecentUsuarioReconocimiento($usuarioReconocimiento, $ultimo)) {
                $ultimo = $usuarioReconocimiento;
            }
        }

        return $ultimo;
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

        if ($activo === false) {
            $this->fechaBajaCenso = new \DateTimeImmutable();
        } else {
            $this->fechaBajaCenso = null;
        }

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

    public function getTipoUsuarioEconomico(): TipoRelacionEconomicaEnum
    {
        return $this->tipoUsuarioEconomico;
    }

    public function setTipoUsuarioEconomico(TipoRelacionEconomicaEnum $tipoUsuarioEconomico): static
    {
        $this->tipoUsuarioEconomico = $tipoUsuarioEconomico;
        return $this;
    }

    public function getTipoPersona(): TipoPersonaEnum {
        return $this->tipoPersona;
    }

    public function setTipoPersona(TipoPersonaEnum $tipoPersona): static {
        $this->tipoPersona = $tipoPersona; return $this;
    }

    public function puedeAcceder(): bool
    {
        return $this->activo;
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

    public function getAntiguedad(): ?int
    {
        return $this->antiguedad;
    }

    public function setAntiguedad(?int $antiguedad): static
    {
        $this->antiguedad = $antiguedad;
        return $this;
    }

    public function getAntiguedadReal(): ?int
    {
        return $this->antiguedadReal;
    }

    public function setAntiguedadReal(?int $antiguedadReal): static
    {
        $this->antiguedadReal = $antiguedadReal;
        return $this;
    }

    public function getFormaPagoPreferida(): ?MetodoPagoEnum
    {
        return $this->formaPagoPreferida;
    }

    public function setFormaPagoPreferida(?MetodoPagoEnum $formaPagoPreferida): static
    {
        $this->formaPagoPreferida = $formaPagoPreferida;
        return $this;
    }

    public function isDebeCambiarPassword(): bool
    {
        return $this->debeCambiarPassword;
    }

    public function setDebeCambiarPassword(bool $debeCambiarPassword): static
    {
        $this->debeCambiarPassword = $debeCambiarPassword;
        return $this;
    }

    public function getPasswordActualizadaAt(): ?\DateTimeImmutable
    {
        return $this->passwordActualizadaAt;
    }

    public function setPasswordActualizadaAt(?\DateTimeImmutable $passwordActualizadaAt): static
    {
        $this->passwordActualizadaAt = $passwordActualizadaAt;
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

    public function isAceptoLopd(): bool
    {
        return $this->aceptoLopd;
    }

    public function setAceptoLopd(bool $acepto): static
    {
        $this->aceptoLopd = $acepto;
        if ($acepto) {
            $this->aceptoLopdAt = new \DateTimeImmutable();
        } else {
            $this->aceptoLopdAt = null;
        }

        return $this;
    }

    public function getAceptoLopdAt(): ?\DateTimeImmutable
    {
        return $this->aceptoLopdAt;
    }

    public function setAceptoLopdAt(?\DateTimeImmutable $aceptoLopdAt): static
    {
        $this->aceptoLopdAt = $aceptoLopdAt;
        $this->aceptoLopd = $aceptoLopdAt !== null;
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

    /** @return Collection<int, UsuarioTemporadaCargo> */
    public function getCargosTemporada(): Collection
    {
        return $this->cargosTemporada;
    }

    /**
     * @return Collection<int, UsuarioReconocimiento>
     */
    public function getUsuarioReconocimientos(): Collection
    {
        return $this->usuarioReconocimientos;
    }

    private function isMoreRecentUsuarioReconocimiento(
        UsuarioReconocimiento $candidate,
        UsuarioReconocimiento $current
    ): bool {
        $candidateFecha = $candidate->getFechaConcesion();
        $currentFecha = $current->getFechaConcesion();

        if ($candidateFecha !== null || $currentFecha !== null) {
            if ($candidateFecha !== null && $currentFecha === null) {
                return true;
            }

            if ($candidateFecha === null && $currentFecha !== null) {
                return false;
            }

            if ($candidateFecha != $currentFecha) {
                return $candidateFecha > $currentFecha;
            }
        }

        $candidateOrden = $candidate->getReconocimiento()->getOrden();
        $currentOrden = $current->getReconocimiento()->getOrden();

        if ($candidateOrden !== $currentOrden) {
            return $candidateOrden > $currentOrden;
        }

        return strcmp((string) $candidate->getId(), (string) $current->getId()) > 0;
    }

    public function addCargoTemporada(UsuarioTemporadaCargo $cargoTemporada): static
    {
        if (!$this->cargosTemporada->contains($cargoTemporada)) {
            $this->cargosTemporada->add($cargoTemporada);
            $cargoTemporada->setUsuario($this);
        }

        return $this;
    }

    public function removeCargoTemporada(UsuarioTemporadaCargo $cargoTemporada): static
    {
        if ($this->cargosTemporada->removeElement($cargoTemporada)) {
            if ($cargoTemporada->getUsuario() === $this) {
                $cargoTemporada->setUsuario(null);
            }
        }

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

    #[Groups(['usuario:read'])]
    /** @return array<Cargo> */
    public function getCargos(): array
    {
        $result = [];

        foreach ($this->cargosTemporada as $utc) {
            $cargo = $utc->getCargo();
            if ($cargo) {
                $result[] = $cargo;
            }
        }

        return $result;
    }

    #[Groups(['usuario:read', 'read_user_admin'])]
    /** @return array<array{usuario: string, tipoRelacion: string}> */
    public function getRelacionUsuarios(): array
    {
        $result = [];

        foreach ($this->getRelacionados() as $rel) {
            $usuarioOrigen = $rel->getUsuarioOrigen();
            $usuarioDestino = $rel->getUsuarioDestino();

            // Determinar el otro usuario en la relación
            $otro = $usuarioOrigen->getId() === $this->getId() ? $usuarioDestino : $usuarioOrigen;

            if (!$otro || !$otro->getId()) {
                continue;
            }

            $result[] = [
                'id' => $otro->getId(),
                'usuario_id' => $otro->getId(),
                'usuario_nombre' => $otro->getNombreCompleto(),
                'tipoRelacion' => $rel->getTipoRelacion()->value,
            ];
        }

        return $result;
    }

    #[Groups(['usuario:read', 'read_user_admin'])]
    public function getTemporadaAplicada(): ?array
    {
        // Return the temporada used for the user's cargos (prefer current year if present)
        foreach ($this->cargosTemporada as $utc) {
            $temporada = $utc->getTemporada();
            if ($temporada !== null) {
                return [
                    'id' => $temporada->getId(),
                    'codigo' => $temporada->getCodigo(),
                    'nombre' => $temporada->getNombre(),
                ];
            }
        }

        return null;
    }

    private function calcularTipoPersona(\DateTimeImmutable $fechaNacimiento): TipoPersonaEnum
    {
        $hoy = new \DateTimeImmutable('today');
        $edad = $fechaNacimiento->diff($hoy)->y;

        return match (true) {
            $edad <= 13 => TipoPersonaEnum::INFANTIL,
            $edad <= 18 => TipoPersonaEnum::CADETE,
            default => TipoPersonaEnum::ADULTO,
        };
    }
}
