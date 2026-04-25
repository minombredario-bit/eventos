<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\EntidadRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
// Optional HTML purifier — if installed via composer (ezyang/htmlpurifier) will be used

#[ORM\Entity(repositoryClass: EntidadRepository::class)]
#[ORM\Table(name: 'entidad')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ENTIDAD_VIEW', object)"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Get(
            uriTemplate: '/entidad/lopd',
            normalizationContext: ['groups' => ['lopd:read']]
        ),
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_SUPERADMIN')"),
        new Patch(security: "is_granted('ENTIDAD_EDIT', object)"),
    ],
    normalizationContext: ['groups' => ['entidad:read']],
    denormalizationContext: ['groups' => ['entidad:write']]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'nombre' => 'partial',
    'slug' => 'exact',
    'tipoEntidad' => 'exact',
    'tipoFiesta' => 'exact',
    'subtipoFestero' => 'exact',
    'temporadaActual' => 'exact',
    'codigoRegistro' => 'exact',
])]
#[ApiFilter(BooleanFilter::class, properties: ['activa', 'censado', 'usaReconocimiento'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt', 'updatedAt'])]
#[ApiFilter(
    OrderFilter::class,
    properties: ['nombre', 'temporadaActual', 'createdAt', 'updatedAt'],
    arguments: ['orderParameterName' => 'order']
)]
class Entidad
{

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['entidad:read'])]
    private ?string $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['entidad:read', 'entidad:write'])]
    #[Assert\NotBlank]
    private string $nombre;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    #[Groups(['entidad:read', 'entidad:write'])]
    #[Assert\NotBlank]
    private string $slug;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['entidad:read', 'entidad:write'])]
    private ?string $descripcion = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['entidad:read', 'entidad:write', 'lopd:read'])]
    private ?string $textoLopd = null;

    #[ORM\ManyToOne(targetEntity: TipoEntidad::class)]
    #[ORM\JoinColumn(name: 'tipo_entidad_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['entidad:read', 'entidad:write'])]
    private ?TipoEntidad $tipoEntidad = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['entidad:read', 'entidad:write'])]
    private ?string $terminologiaSocio = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['entidad:read', 'entidad:write'])]
    private ?string $terminologiaEvento = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['entidad:read'])]
    private ?string $logo = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['entidad:read', 'entidad:write'])]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $emailContacto;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['entidad:read', 'entidad:write'])]
    private ?string $telefono = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['entidad:read', 'entidad:write'])]
    private ?string $direccion = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
    #[Groups(['entidad:read'])]
    private string $codigoRegistro;

    #[ORM\Column(type: Types::STRING, length: 10)]
    #[Groups(['entidad:read', 'entidad:write'])]
    private string $temporadaActual;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['entidad:read', 'entidad:write'])]
    private bool $activa = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['entidad:read', 'entidad:write'])]
    private bool $censado = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['entidad:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['entidad:read'])]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Usuario> */
    #[ORM\OneToMany(targetEntity: Usuario::class, mappedBy: 'entidad')]
    private Collection $usuarios;

    /** @var Collection<int, Evento> */
    #[ORM\OneToMany(targetEntity: Evento::class, mappedBy: 'entidad')]
    private Collection $eventos;

    /** @var Collection<int, Usuario> */
    #[ORM\ManyToMany(targetEntity: Usuario::class, inversedBy: 'entidadesAdmin')]
    #[ORM\JoinTable(name: 'entidad_admins')]
    private Collection $admins;

    /** @var Collection<int, Cargo> */
    #[ORM\OneToMany(targetEntity: Cargo::class, mappedBy: 'entidad')]
    private Collection $cargos;

    /** @var Collection<int, EntidadCargo> */
    #[ORM\OneToMany(targetEntity: EntidadCargo::class, mappedBy: 'entidad', cascade: [
        'persist',
        'remove',
    ], orphanRemoval: true)]
    #[Groups(['entidad:read'])]
    private Collection $entidadCargos;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['entidad:read', 'entidad:write'])]
    private bool $usaReconocimiento = true;

    /** @var Collection<int, TemporadaEntidad> */
    #[ORM\OneToMany(
        targetEntity: TemporadaEntidad::class,
        mappedBy: 'entidad',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $temporadas;

    /** @var Collection<int, Reconocimiento> */
    #[ORM\OneToMany(targetEntity: Reconocimiento::class, mappedBy: 'entidad')]
    private Collection $reconocimientos;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->usuarios = new ArrayCollection();
        $this->eventos = new ArrayCollection();
        $this->admins = new ArrayCollection();
        $this->cargos = new ArrayCollection();
        $this->entidadCargos = new ArrayCollection();
        $this->temporadas = new ArrayCollection();
        $this->reconocimientos = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;

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

    public function getTextoLopd(): ?string
    {
        return $this->textoLopd;
    }

    public function setTextoLopd(?string $textoLopd): static
    {
        // If HTMLPurifier is available, sanitize HTML to avoid XSS when rendering later.
        if ($textoLopd !== null && class_exists('\HTMLPurifier')) {
            // Create default config; if HTMLPurifier not available this block is skipped.
            $config = \HTMLPurifier_Config::createDefault();
            // Allow some basic tags -- default config is OK, but host app can customize if needed
            $purifier = new \HTMLPurifier($config);
            $this->textoLopd = $purifier->purify($textoLopd);
        } else {
            $this->textoLopd = $textoLopd;
        }

        return $this;
    }

    public function getTipoEntidad(): ?TipoEntidad
    {
        return $this->tipoEntidad;
    }

    public function setTipoEntidad(?TipoEntidad $tipoEntidad): static
    {
        $this->tipoEntidad = $tipoEntidad;

        return $this;
    }

    public function getTerminologiaSocio(): ?string
    {
        return $this->terminologiaSocio;
    }

    public function setTerminologiaSocio(?string $terminologiaSocio): static
    {
        $this->terminologiaSocio = $terminologiaSocio;

        return $this;
    }

    public function getTerminologiaEvento(): ?string
    {
        return $this->terminologiaEvento;
    }

    public function setTerminologiaEvento(?string $terminologiaEvento): static
    {
        $this->terminologiaEvento = $terminologiaEvento;

        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function getEmailContacto(): string
    {
        return $this->emailContacto;
    }

    public function setEmailContacto(string $emailContacto): static
    {
        $this->emailContacto = $emailContacto;

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

    public function getDireccion(): ?string
    {
        return $this->direccion;
    }

    public function setDireccion(?string $direccion): static
    {
        $this->direccion = $direccion;

        return $this;
    }

    public function getCodigoRegistro(): string
    {
        return $this->codigoRegistro;
    }

    public function setCodigoRegistro(string $codigoRegistro): static
    {
        $this->codigoRegistro = $codigoRegistro;

        return $this;
    }

    public function getTemporadaActual(): string
    {
        return $this->temporadaActual;
    }

    public function setTemporadaActual(string $temporadaActual): static
    {
        $this->temporadaActual = $temporadaActual;

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

    public function isUsaReconocimiento(): bool
    {
        return $this->usaReconocimiento;
    }

    public function setUsaReconocimiento(bool $usaReconocimiento): static
    {
        $this->usaReconocimiento = $usaReconocimiento;

        return $this;
    }

    public function isCensado(): bool
    {
        return $this->censado;
    }

    public function setCensado(bool $censado): static
    {
        $this->censado = $censado;

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

    /** @return Collection<int, Usuario> */
    public function getUsuarios(): Collection
    {
        return $this->usuarios;
    }

    /** @return Collection<int, Evento> */
    public function getEventos(): Collection
    {
        return $this->eventos;
    }

    /** @return Collection<int, Usuario> */
    public function getAdmins(): Collection
    {
        return $this->admins;
    }

    public function addAdmin(Usuario $admin): static
    {
        if (!$this->admins->contains($admin)) {
            $this->admins->add($admin);
        }

        return $this;
    }

    public function removeAdmin(Usuario $admin): static
    {
        $this->admins->removeElement($admin);

        return $this;
    }

    /** @return Collection<int, Cargo> */
    public function getCargos(): Collection
    {
        return $this->cargos;
    }

    /** @return Collection<int, EntidadCargo> */
    public function getEntidadCargos(): Collection
    {
        return $this->entidadCargos;
    }

    /** @return Collection<int, TemporadaEntidad> */
    public function getTemporadas(): Collection
    {
        return $this->temporadas;
    }

    /** @return Collection<int, Reconocimiento> */
    public function getReconocimientos(): Collection
    {
        return $this->reconocimientos;
    }
}
