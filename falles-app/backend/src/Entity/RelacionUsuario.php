<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use App\Enum\TipoRelacionEnum;
use App\Repository\RelacionUsuarioRepository;
use App\State\RelacionUsuarioProvider;
use App\State\RelacionUsuarioProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RelacionUsuarioRepository::class)]
#[ORM\Table(name: 'relacion_usuario')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => ['relacion:read'], 'enable_max_depth' => 2],
    denormalizationContext: ['groups' => ['relacion:write']],
    operations: [
        new GetCollection(
            uriTemplate: '/usuarios/{id}/relaciones',
            uriVariables: [
                'id' => new Link(
                    fromClass: Usuario::class,
                    identifiers: ['id'],
                    toProperty: 'usuarioOrigen'
                )
            ],
            provider: RelacionUsuarioProvider::class,
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => ['relacion:read'], 'enable_max_depth' => 2],
        ),
        new Post(
            uriTemplate: '/usuarios/{id}/relaciones',
            uriVariables: [
                'id' => new Link(
                    fromClass: Usuario::class,
                    identifiers: ['id'],
                    toProperty: 'usuarioOrigen'
                )
            ],
            processor: RelacionUsuarioProcessor::class,
            security: "is_granted('ROLE_USER')",
            denormalizationContext: ['groups' => ['relacion:write']],
        ),
        new Delete(
            uriTemplate: '/relaciones/{id}',
            security: "is_granted('RELACION_DELETE', object)",
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'usuarioOrigen' => 'exact',
    'usuarioOrigen.id' => 'exact',
    'usuarioDestino' => 'exact',
    'usuarioDestino.id' => 'exact',
    'tipoRelacion' => 'exact',
])]
#[ApiFilter(
    OrderFilter::class,
    properties: ['createdAt'],
    arguments: ['orderParameterName' => 'order']
)]
class RelacionUsuario
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['relacion:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Usuario::class, inversedBy: 'relacionesOrigen')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['relacion:read', 'relacion:write'])]
    #[Assert\NotNull]
    private Usuario $usuarioOrigen;

    #[ORM\ManyToOne(targetEntity: Usuario::class, inversedBy: 'relacionesDestino')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['relacion:read', 'relacion:write'])]
    #[Assert\NotNull]
    private Usuario $usuarioDestino;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: TipoRelacionEnum::class)]
    #[Groups(['relacion:read', 'relacion:write'])]
    #[Assert\NotNull]
    private TipoRelacionEnum $tipoRelacion;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['relacion:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id        = Uuid::uuid4();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUsuarioOrigen(): Usuario
    {
        return $this->usuarioOrigen;
    }

    public function setUsuarioOrigen(Usuario $usuarioOrigen): static
    {
        $this->usuarioOrigen = $usuarioOrigen;
        return $this;
    }

    public function getUsuarioDestino(): Usuario
    {
        return $this->usuarioDestino;
    }

    public function setUsuarioDestino(Usuario $usuarioDestino): static
    {
        $this->usuarioDestino = $usuarioDestino;
        return $this;
    }

    public function getTipoRelacion(): TipoRelacionEnum
    {
        return $this->tipoRelacion;
    }

    public function setTipoRelacion(TipoRelacionEnum $tipoRelacion): static
    {
        $this->tipoRelacion = $tipoRelacion;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
