<?php

namespace App\Entity;

use App\Repository\SeleccionParticipantesEventoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity(repositoryClass: SeleccionParticipantesEventoRepository::class)]
#[ORM\Table(name: 'seleccion_participantes_evento')]
#[ORM\UniqueConstraint(name: 'uniq_seleccion_usuario_evento', columns: ['usuario_id', 'evento_id'])]
#[ORM\HasLifecycleCallbacks]
class SeleccionParticipantesEvento
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Usuario::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Usuario $usuario;

    #[ORM\ManyToOne(targetEntity: Evento::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Evento $evento;

    /**
     * @var list<array<string, mixed>>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $participantes = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
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
