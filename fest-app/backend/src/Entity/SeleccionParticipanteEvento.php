<?php

namespace App\Entity;

use App\Repository\SeleccionParticipanteEventoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use App\State\SeleccionParticipanteEventoPostProcessor;
use App\State\SeleccionParticipantesEventoDeleteProcessor;

#[ORM\Entity(repositoryClass: SeleccionParticipanteEventoRepository::class)]
#[ORM\Table(
    name: 'seleccion_participante_evento',
    indexes: [
        new ORM\Index(name: 'idx_sel_part_evento_evento', columns: ['evento_id']),
        new ORM\Index(name: 'idx_sel_part_evento_inscrito_por', columns: ['inscrito_por_usuario_id']),
        new ORM\Index(name: 'idx_sel_part_evento_usuario', columns: ['usuario_id']),
        new ORM\Index(name: 'idx_sel_part_evento_invitado', columns: ['invitado_id']),
    ],
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_sel_part_evento_usuario', columns: ['evento_id', 'usuario_id']),
        new ORM\UniqueConstraint(name: 'uniq_sel_part_evento_invitado', columns: ['evento_id', 'invitado_id']),
    ]
)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Post(
            security: "is_granted('ROLE_USER')",
            denormalizationContext: ['groups' => ['seleccion_participante_evento:write']],
            normalizationContext: ['groups' => ['seleccion_participante_evento:read']],
            processor: SeleccionParticipanteEventoPostProcessor::class,
        ),
        // Nested POST for creating a selection under an evento: /eventos/{eventoId}/seleccion_participantes
        new Post(
            uriTemplate: '/eventos/{eventoId}/seleccion_participantes',
            security: "is_granted('ROLE_USER')",
            denormalizationContext: ['groups' => ['seleccion_participante_evento:write']],
            normalizationContext: ['groups' => ['seleccion_participante_evento:read']],
            processor: SeleccionParticipanteEventoPostProcessor::class,
        ),
        // Nested DELETE for removing selections/inscripciones for the logged user in an evento
        new Delete(
            uriTemplate: '/eventos/{eventoId}/seleccion_participantes/{id}',
            security: "is_granted('ROLE_USER')",
            processor: SeleccionParticipantesEventoDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['seleccion_participante_evento:read']],
    denormalizationContext: ['groups' => ['seleccion_participante_evento:write']],
)]
class SeleccionParticipanteEvento
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['seleccion_participante_evento:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Evento::class)]
    #[ORM\JoinColumn(name: 'evento_id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['seleccion_participante_evento:read', 'seleccion_participante_evento:write'])]
    #[Assert\NotNull]
    private Evento $evento;

    #[ORM\ManyToOne(targetEntity: Usuario::class)]
    #[ORM\JoinColumn(name: 'inscrito_por_usuario_id', nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['seleccion_participante_evento:read', 'seleccion_participante_evento:write'])]
    #[Assert\NotNull]
    private Usuario $inscritoPorUsuario;

    #[ORM\ManyToOne(targetEntity: Usuario::class)]
    #[ORM\JoinColumn(name: 'usuario_id', nullable: true, onDelete: 'RESTRICT')]
    #[Groups(['seleccion_participante_evento:read', 'seleccion_participante_evento:write'])]
    private ?Usuario $usuario = null;

    #[ORM\ManyToOne(targetEntity: Invitado::class)]
    #[ORM\JoinColumn(name: 'invitado_id', nullable: true, onDelete: 'RESTRICT')]
    #[Groups(['seleccion_participante_evento:read', 'seleccion_participante_evento:write'])]
    private ?Invitado $invitado = null;

    /** @var Collection<int, SeleccionParticipanteEventoLinea> */
    #[ORM\OneToMany(mappedBy: 'seleccionParticipanteEvento', targetEntity: SeleccionParticipanteEventoLinea::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lineas;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['seleccion_participante_evento:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['seleccion_participante_evento:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->lineas = new ArrayCollection();
    }

    #[Assert\IsTrue(message: 'Debe existir exactamente un participante: usuario o invitado.')]
    public function isParticipanteValido(): bool
    {
        return ($this->usuario === null) !== ($this->invitado === null);
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

    public function getEvento(): Evento
    {
        return $this->evento;
    }

    public function setEvento(Evento $evento): static
    {
        $this->evento = $evento;

        return $this;
    }

    public function getInscritoPorUsuario(): Usuario
    {
        return $this->inscritoPorUsuario;
    }

    public function setInscritoPorUsuario(Usuario $inscritoPorUsuario): static
    {
        $this->inscritoPorUsuario = $inscritoPorUsuario;

        return $this;
    }

    public function getUsuario(): ?Usuario
    {
        return $this->usuario;
    }

    public function setUsuario(?Usuario $usuario): static
    {
        $this->usuario = $usuario;

        return $this;
    }

    public function getInvitado(): ?Invitado
    {
        return $this->invitado;
    }

    public function setInvitado(?Invitado $invitado): static
    {
        $this->invitado = $invitado;

        return $this;
    }

    /** @return Collection<int, SeleccionParticipanteEventoLinea> */
    public function getLineas(): Collection
    {
        return $this->lineas;
    }

    public function addLinea(SeleccionParticipanteEventoLinea $linea): static
    {
        if (!$this->lineas->contains($linea)) {
            $this->lineas->add($linea);
            $linea->setSeleccionParticipanteEvento($this);
        }

        return $this;
    }

    public function removeLinea(SeleccionParticipanteEventoLinea $linea): static
    {
        if ($this->lineas->removeElement($linea) && $linea->getSeleccionParticipanteEvento() === $this) {
            $linea->setSeleccionParticipanteEvento(null);
        }

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
