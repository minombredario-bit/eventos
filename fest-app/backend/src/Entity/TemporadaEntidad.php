<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'temporada_entidad')]
#[ORM\UniqueConstraint(name: 'uniq_temporada_entidad_codigo', columns: ['entidad_id', 'codigo'])]
class TemporadaEntidad
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['temporada:read', 'usuario:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Entidad::class, inversedBy: 'temporadas')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['temporada:read', 'temporada:write'])]
    #[Assert\NotNull]
    private Entidad $entidad;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Groups(['temporada:read', 'temporada:write', 'usuario:read'])]
    #[Assert\NotBlank]
    private string $codigo;

    #[ORM\Column(type: Types::STRING, length: 120, nullable: true)]
    #[Groups(['temporada:read', 'temporada:write'])]
    private ?string $nombre = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['temporada:read', 'temporada:write'])]
    private ?\DateTimeImmutable $fechaInicio = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['temporada:read', 'temporada:write'])]
    private ?\DateTimeImmutable $fechaFin = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['temporada:read', 'temporada:write'])]
    private bool $cerrada = false;

    /** @var Collection<int, UsuarioTemporadaCargo> */
    #[ORM\OneToMany(
        targetEntity: UsuarioTemporadaCargo::class,
        mappedBy: 'temporada',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $usuariosCargo;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->usuariosCargo = new ArrayCollection();
    }

    public function getId(): ?string { return $this->id; }

    public function getEntidad(): Entidad { return $this->entidad; }
    public function setEntidad(Entidad $entidad): static { $this->entidad = $entidad; return $this; }

    public function getCodigo(): string { return $this->codigo; }
    public function setCodigo(string $codigo): static { $this->codigo = $codigo; return $this; }

    public function getNombre(): ?string { return $this->nombre; }
    public function setNombre(?string $nombre): static { $this->nombre = $nombre; return $this; }

    public function getFechaInicio(): ?\DateTimeImmutable { return $this->fechaInicio; }
    public function setFechaInicio(?\DateTimeImmutable $fechaInicio): static
    {
        $this->fechaInicio = $fechaInicio;
        return $this;
    }

    public function getFechaFin(): ?\DateTimeImmutable { return $this->fechaFin; }
    public function setFechaFin(?\DateTimeImmutable $fechaFin): static
    {
        $this->fechaFin = $fechaFin;
        return $this;
    }

    public function isCerrada(): bool { return $this->cerrada; }
    public function setCerrada(bool $cerrada): static { $this->cerrada = $cerrada; return $this; }

    /** @return Collection<int, UsuarioTemporadaCargo> */
    public function getUsuariosCargo(): Collection
    {
        return $this->usuariosCargo;
    }
}
