<?php

namespace App\Entity;

use App\Repository\UsuarioReconocimientoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: UsuarioReconocimientoRepository::class)]
#[ORM\Table(name: 'usuario_reconocimiento')]
#[ORM\UniqueConstraint(name: 'uniq_usuario_reconocimiento', columns: ['usuario_id', 'reconocimiento_id'])]
class UsuarioReconocimiento
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['usuario_reconocimiento:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Usuario::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['usuario_reconocimiento:read', 'usuario_reconocimiento:write'])]
    private Usuario $usuario;

    #[ORM\ManyToOne(targetEntity: Entidad::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['usuario_reconocimiento:read', 'usuario_reconocimiento:write'])]
    private Entidad $entidad;

    #[ORM\ManyToOne(targetEntity: Reconocimiento::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['usuario_reconocimiento:read', 'usuario_reconocimiento:write'])]
    private Reconocimiento $reconocimiento;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Groups(['usuario_reconocimiento:read', 'usuario_reconocimiento:write'])]
    private ?string $temporadaCodigo = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['usuario_reconocimiento:read', 'usuario_reconocimiento:write'])]
    private ?\DateTimeImmutable $fechaConcesion = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['usuario_reconocimiento:read', 'usuario_reconocimiento:write'])]
    private ?string $observaciones = null;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
    }

    public function getId(): ?string { return $this->id; }

    public function getUsuario(): Usuario { return $this->usuario; }
    public function setUsuario(Usuario $usuario): static { $this->usuario = $usuario; return $this; }

    public function getEntidad(): Entidad { return $this->entidad; }
    public function setEntidad(Entidad $entidad): static { $this->entidad = $entidad; return $this; }

    public function getReconocimiento(): Reconocimiento { return $this->reconocimiento; }
    public function setReconocimiento(Reconocimiento $reconocimiento): static { $this->reconocimiento = $reconocimiento; return $this; }

    public function getTemporadaCodigo(): ?string { return $this->temporadaCodigo; }
    public function setTemporadaCodigo(?string $temporadaCodigo): static { $this->temporadaCodigo = $temporadaCodigo; return $this; }

    public function getFechaConcesion(): ?\DateTimeImmutable { return $this->fechaConcesion; }
    public function setFechaConcesion(?\DateTimeImmutable $fechaConcesion): static { $this->fechaConcesion = $fechaConcesion; return $this; }

    public function getObservaciones(): ?string { return $this->observaciones; }
    public function setObservaciones(?string $observaciones): static { $this->observaciones = $observaciones; return $this; }
}
