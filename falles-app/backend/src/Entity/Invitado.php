<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\TipoPersonaEnum;
use App\Repository\InvitadoRepository;
use App\State\InvitadoDeleteProcessor;
use App\State\InvitadoPostProcessor;
use App\State\InvitadoProvider;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InvitadoRepository::class)]
#[ORM\Table(name: 'invitado')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => ['invitado:read']],
    denormalizationContext: ['groups' => ['invitado:write']],
    operations: [
        new Get(
            provider: InvitadoProvider::class,
            security: "is_granted('ROLE_ADMIN_ENTIDAD') or object.getCreadoPor() == user"
        ),
        new GetCollection(
            provider: InvitadoProvider::class,
            security: "is_granted('ROLE_ADMIN_ENTIDAD')"
        ),
        new Post(
            processor: InvitadoPostProcessor::class,
            security: "is_granted('ROLE_USER')",
            securityPostDenormalize: "object.getCreadoPor() == user"
        ),
        new Patch(
            provider: InvitadoProvider::class,
            security: "is_granted('ROLE_ADMIN_ENTIDAD') or object.getCreadoPor() == user"
        ),
        new Delete(
            provider: InvitadoProvider::class,
            processor: InvitadoDeleteProcessor::class,
            security: "is_granted('ROLE_ADMIN_ENTIDAD') or object.getCreadoPor() == user"
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'creadoPor' => 'exact',
    'creadoPor.id' => 'exact',
    'evento' => 'exact',
    'evento.id' => 'exact',
    'tipoPersona' => 'exact',
    'nombre' => 'partial',
    'apellidos' => 'partial',
    'nombreCompleto' => 'partial',
])]
#[ApiFilter(
    OrderFilter::class,
    properties: ['createdAt', 'nombreCompleto', 'nombre', 'apellidos'],
    arguments: ['orderParameterName' => 'order']
)]
class Invitado
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['invitado:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Usuario::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['invitado:read', 'invitado:write'])]
    private Usuario $creadoPor; // El fallero que lo da de alta

    #[ORM\ManyToOne(targetEntity: Evento::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['invitado:read', 'invitado:write'])]
    private Evento $evento;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['invitado:read', 'invitado:write'])]
    #[Assert\NotBlank]
    private string $nombre;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['invitado:read', 'invitado:write'])]
    #[Assert\NotBlank]
    private string $apellidos;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['invitado:read'])]
    private string $nombreCompleto;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: TipoPersonaEnum::class)]
    #[Groups(['invitado:read', 'invitado:write'])]
    #[Assert\NotNull]
    private TipoPersonaEnum $tipoPersona;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['invitado:read', 'invitado:write'])]
    private ?string $observaciones = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['invitado:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct()
    {
        $this->id        = Uuid::uuid4();
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function syncNombreCompleto(): void
    {
        $this->nombreCompleto = trim($this->nombre . ' ' . $this->apellidos);
    }

    public function getId(): ?string { 
        return $this->id; 
    }

    public function getCreadoPor(): Usuario { 
        return $this->creadoPor; 
    }

    public function setCreadoPor(Usuario $creadoPor): static { 
        $this->creadoPor = $creadoPor; return $this; 
    }

    public function getEvento(): Evento { 
        return $this->evento; 
    }

    public function setEvento(Evento $evento): static { 
        $this->evento = $evento; return $this; 
    }

    public function getNombre(): string { 
        return $this->nombre; 
    }

    public function setNombre(string $nombre): static { 
        $this->nombre = $nombre; return $this; 
    }

    public function getApellidos(): string { 
        return $this->apellidos; 
    }

    public function setApellidos(string $apellidos): static { 
        $this->apellidos = $apellidos; return $this; 
    }

    public function getNombreCompleto(): string { 
        return $this->nombreCompleto; 
    }

    public function getTipoPersona(): TipoPersonaEnum { 
        return $this->tipoPersona; 
    }

    public function setTipoPersona(TipoPersonaEnum $tipoPersona): static { 
        $this->tipoPersona = $tipoPersona; return $this; 
    }

    public function getObservaciones(): ?string { 
        return $this->observaciones; 
    }

    public function setObservaciones(?string $observaciones): static { 
        $this->observaciones = $observaciones; return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable { 
        return $this->createdAt; 
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }
}
