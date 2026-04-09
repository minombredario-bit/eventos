<?php

namespace App\Entity;

use App\Repository\SeleccionParticipanteEventoLineaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SeleccionParticipanteEventoLineaRepository::class)]
#[ORM\Table(
    name: 'seleccion_participante_evento_linea',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_sel_part_evento_menu_usuario', columns: ['evento_id', 'menu_id', 'usuario_id']),
        new ORM\UniqueConstraint(name: 'uniq_sel_part_evento_menu_invitado', columns: ['evento_id', 'menu_id', 'invitado_id']),
    ],
    indexes: [
        new ORM\Index(name: 'idx_sel_part_evento_linea_sel_part', columns: ['seleccion_participante_evento_id']),
        new ORM\Index(name: 'idx_sel_part_evento_evento', columns: ['evento_id']),
        new ORM\Index(name: 'idx_sel_part_evento_menu', columns: ['menu_id']),
        new ORM\Index(name: 'idx_sel_part_evento_usuario', columns: ['usuario_id']),
        new ORM\Index(name: 'idx_sel_part_evento_invitado', columns: ['invitado_id']),
        new ORM\Index(name: 'idx_sel_part_evento_inscripcion_linea', columns: ['inscripcion_linea_id']),
    ]
)]
#[ORM\HasLifecycleCallbacks]
class SeleccionParticipanteEventoLinea
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['seleccion_participante_evento:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: SeleccionParticipanteEvento::class, inversedBy: 'lineas')]
    #[ORM\JoinColumn(name: 'seleccion_participante_evento_id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?SeleccionParticipanteEvento $seleccionParticipanteEvento = null;

    #[ORM\ManyToOne(targetEntity: Evento::class)]
    #[ORM\JoinColumn(name: 'evento_id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private Evento $evento;

    #[ORM\ManyToOne(targetEntity: Usuario::class)]
    #[ORM\JoinColumn(name: 'usuario_id', nullable: true, onDelete: 'RESTRICT')]
    private ?Usuario $usuario = null;

    #[ORM\ManyToOne(targetEntity: Invitado::class)]
    #[ORM\JoinColumn(name: 'invitado_id', nullable: true, onDelete: 'RESTRICT')]
    private ?Invitado $invitado = null;

    #[ORM\ManyToOne(targetEntity: MenuEvento::class)]
    #[ORM\JoinColumn(name: 'menu_id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private MenuEvento $menu;

    #[ORM\ManyToOne(targetEntity: InscripcionLinea::class)]
    #[ORM\JoinColumn(name: 'inscripcion_linea_id', nullable: true, onDelete: 'SET NULL')]
    private ?InscripcionLinea $inscripcionLinea = null;

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

    public function getSeleccionParticipanteEvento(): ?SeleccionParticipanteEvento
    {
        return $this->seleccionParticipanteEvento;
    }

    public function setSeleccionParticipanteEvento(?SeleccionParticipanteEvento $seleccionParticipanteEvento): static
    {
        $this->seleccionParticipanteEvento = $seleccionParticipanteEvento;

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

    public function getMenu(): MenuEvento
    {
        return $this->menu;
    }

    public function getActividad(): MenuEvento
    {
        return $this->menu;
    }

    public function setMenu(MenuEvento $menu): static
    {
        $this->menu = $menu;

        return $this;
    }

    public function setActividad(MenuEvento $actividad): static
    {
        $this->menu = $actividad;

        return $this;
    }

    public function getInscripcionLinea(): ?InscripcionLinea
    {
        return $this->inscripcionLinea;
    }

    public function setInscripcionLinea(?InscripcionLinea $inscripcionLinea): static
    {
        $this->inscripcionLinea = $inscripcionLinea;

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
