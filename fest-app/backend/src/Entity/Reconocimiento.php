<?php

namespace App\Entity;

use App\Repository\ReconocimientoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ReconocimientoRepository::class)]
#[ORM\Table(name: 'reconocimiento')]
#[ORM\UniqueConstraint(name: 'uniq_reconocimiento_entidad_codigo', columns: ['entidad_id', 'codigo'])]
class Reconocimiento
{
    public const TIPO_ANTIGUEDAD = 'antiguedad';
    public const TIPO_DIRECTIVO = 'directivo';
    public const TIPO_INFANTIL = 'infantil';
    public const TIPO_LIBRE = 'libre';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['reconocimiento:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Entidad::class, inversedBy: 'reconocimientos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['reconocimiento:read', 'reconocimiento:write'])]
    private Entidad $entidad;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Groups(['reconocimiento:read', 'reconocimiento:write'])]
    private string $codigo;

    #[ORM\Column(type: Types::STRING, length: 150)]
    #[Groups(['reconocimiento:read', 'reconocimiento:write'])]
    private string $nombre;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Groups(['reconocimiento:read', 'reconocimiento:write'])]
    private string $tipo = self::TIPO_ANTIGUEDAD;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Groups(['reconocimiento:read', 'reconocimiento:write'])]
    private int $orden = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2, nullable: true)]
    #[Groups(['reconocimiento:read', 'reconocimiento:write'])]
    private ?string $minAntiguedad = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2, nullable: true)]
    #[Groups(['reconocimiento:read', 'reconocimiento:write'])]
    private ?string $minAntiguedadDirectivo = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['reconocimiento:read', 'reconocimiento:write'])]
    private bool $requiereDirectivo = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['reconocimiento:read', 'reconocimiento:write'])]
    private bool $requiereAnterior = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['reconocimiento:read', 'reconocimiento:write'])]
    private bool $activo = true;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
    }

    public function getId(): ?string
    {
        return $this->id;
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

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function setCodigo(string $codigo): static
    {
        $this->codigo = $codigo;

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

    public function getTipo(): string
    {
        return $this->tipo;
    }

    public function setTipo(string $tipo): static
    {
        $this->tipo = $tipo;

        return $this;
    }

    public function getOrden(): int
    {
        return $this->orden;
    }

    public function setOrden(int $orden): static
    {
        $this->orden = $orden;

        return $this;
    }

    public function getMinAntiguedad(): ?float
    {
        return $this->minAntiguedad !== null ? (float)$this->minAntiguedad : null;
    }

    public function setMinAntiguedad(?float $value): static
    {
        $this->minAntiguedad = $value !== null ? number_format($value, 2, '.', '') : null;

        return $this;
    }

    public function getMinAntiguedadDirectivo(): ?float
    {
        return $this->minAntiguedadDirectivo !== null ? (float)$this->minAntiguedadDirectivo : null;
    }

    public function setMinAntiguedadDirectivo(?float $value): static
    {
        $this->minAntiguedadDirectivo = $value !== null ? number_format($value, 2, '.', '') : null;

        return $this;
    }

    public function isRequiereDirectivo(): bool
    {
        return $this->requiereDirectivo;
    }

    public function setRequiereDirectivo(bool $requiereDirectivo): static
    {
        $this->requiereDirectivo = $requiereDirectivo;

        return $this;
    }

    public function isRequiereAnterior(): bool
    {
        return $this->requiereAnterior;
    }

    public function setRequiereAnterior(bool $requiereAnterior): static
    {
        $this->requiereAnterior = $requiereAnterior;

        return $this;
    }

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): static
    {
        $this->activo = $activo;

        return $this;
    }
}
