<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Dto\SeleccionParticipantesInput;
use App\Dto\SeleccionParticipantesView;
use App\Repository\SeleccionParticipantesEventoRepository;
use App\State\SeleccionParticipantesEventoDeleteProcessor;
use App\State\SeleccionParticipantesEventoProvider;
use App\State\SeleccionParticipantesEventoPutProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: SeleccionParticipantesEventoRepository::class)]
#[ORM\Table(name: 'seleccion_participantes_evento')]
#[ORM\UniqueConstraint(name: 'uniq_seleccion_usuario_evento', columns: ['usuario_id', 'evento_id'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => ['seleccion_participantes_evento:read']],
    denormalizationContext: ['groups' => ['seleccion_participantes_evento:write']],
    operations: [
        new Get(
            uriTemplate: '/eventos/{id}/seleccion_participantes',
            uriVariables: [
                'id' => new Link(fromClass: Evento::class, identifiers: ['id'], toProperty: 'evento'),
            ],
            provider: SeleccionParticipantesEventoProvider::class,
            output: SeleccionParticipantesView::class,
            normalizationContext: ['groups' => ['seleccion_participantes_evento_endpoint:read']],
            security: "is_granted('ROLE_USER')"
        ),
        new Put(
            uriTemplate: '/eventos/{id}/seleccion_participantes',
            uriVariables: [
                'id' => new Link(fromClass: Evento::class, identifiers: ['id'], toProperty: 'evento'),
            ],
            input: SeleccionParticipantesInput::class,
            output: SeleccionParticipantesView::class,
            processor: SeleccionParticipantesEventoPutProcessor::class,
            denormalizationContext: ['groups' => ['seleccion_participantes_evento_endpoint:write']],
            normalizationContext: ['groups' => ['seleccion_participantes_evento_endpoint:read']],
            security: "is_granted('ROLE_USER')"
        ),
        new Delete(
            uriTemplate: '/eventos/{id}/seleccion_participantes',
            uriVariables: [
                'id' => new Link(fromClass: Evento::class, identifiers: ['id'], toProperty: 'evento'),
            ],
            processor: SeleccionParticipantesEventoDeleteProcessor::class,
            output: false,
            status: 204,
            security: "is_granted('ROLE_USER')"
        ),
        new Get(security: "is_granted('ROLE_USER') and object.getUsuario() == user"),
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_USER')"),
        new Patch(security: "is_granted('ROLE_USER') and object.getUsuario() == user"),
        new Delete(security: "is_granted('ROLE_USER') and object.getUsuario() == user"),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'usuario' => 'exact',
    'usuario.id' => 'exact',
    'evento' => 'exact',
    'evento.id' => 'exact',
])]
#[ApiFilter(
    OrderFilter::class,
    properties: ['createdAt', 'updatedAt'],
    arguments: ['orderParameterName' => 'order']
)]
class SeleccionParticipantesEvento
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['seleccion_participantes_evento:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Usuario::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['seleccion_participantes_evento:read', 'seleccion_participantes_evento:write'])]
    private Usuario $usuario;

    #[ORM\ManyToOne(targetEntity: Evento::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['seleccion_participantes_evento:read', 'seleccion_participantes_evento:write'])]
    private Evento $evento;

    /**
     * @var list<array<string, mixed>>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['seleccion_participantes_evento:read', 'seleccion_participantes_evento:write'])]
    private array $participantes = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['seleccion_participantes_evento:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['seleccion_participantes_evento:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
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

    public function getUsuario(): Usuario
    {
        return $this->usuario;
    }

    public function setUsuario(Usuario $usuario): static
    {
        $this->usuario = $usuario;

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

    /**
     * @return list<array<string, mixed>>
     */
    public function getParticipantes(): array
    {
        return $this->participantes;
    }

    /**
     * @param list<array<string, mixed>> $participantes
     */
    public function setParticipantes(array $participantes): static
    {
        $this->participantes = $participantes;

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
}
