<?php

namespace App\Entity;

use App\Enum\TipoPersonaEnum;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'usuario_temporada_cargo')]
#[ORM\UniqueConstraint(name: 'uniq_usuario_temporada_cargo', columns: ['usuario_id', 'temporada_id', 'entidad_cargo_id'])]
class UsuarioTemporadaCargo
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['usuario:read', 'cargo:read', 'temporada:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Usuario::class, inversedBy: 'cargosTemporada')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['usuario:read', 'usuario:write'])]
    #[Assert\NotNull]
    private ?Usuario $usuario = null;

    #[ORM\ManyToOne(targetEntity: TemporadaEntidad::class, inversedBy: 'usuariosCargo')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['usuario:read', 'usuario:write'])]
    #[Assert\NotNull]
    private ?TemporadaEntidad $temporada = null;

    /**
     * Punto de entrada único para cualquier tipo de cargo (oficial o interno).
     * Nunca apuntes directamente a Cargo o CargoMaster desde aquí.
     */
    #[ORM\ManyToOne(targetEntity: EntidadCargo::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['usuario:read', 'usuario:write'])]
    #[Assert\NotNull]
    private ?EntidadCargo $entidadCargo = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['usuario:read', 'usuario:write'])]
    private bool $principal = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['usuario:read', 'usuario:write'])]
    private bool $computaAntiguedad = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['usuario:read', 'usuario:write'])]
    private bool $computaReconocimiento = true;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2, options: ['default' => '0.00'])]
    #[Groups(['usuario:read', 'usuario:write'])]
    private string $aniosExtraAplicados = '0.00';

    #[ORM\Column(type: Types::STRING, length: 20, enumType: TipoPersonaEnum::class)]
    #[Groups(['usuario:read', 'usuario:write'])]
    private TipoPersonaEnum $tipoPersona = TipoPersonaEnum::ADULTO;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    #[Groups(['usuario:read', 'usuario:write'])]
    private int $orden = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['usuario:read', 'usuario:write'])]
    private ?string $observaciones = null;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
    }

    public function getId(): ?string
    {
        return $this->id;
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

    public function getTemporada(): ?TemporadaEntidad
    {
        return $this->temporada;
    }

    public function setTemporada(?TemporadaEntidad $temporada): static
    {
        $this->temporada = $temporada;

        return $this;
    }

    public function getEntidadCargo(): ?EntidadCargo
    {
        return $this->entidadCargo;
    }

    public function setEntidadCargo(?EntidadCargo $entidadCargo): static
    {
        $this->entidadCargo = $entidadCargo;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Helpers de conveniencia: delegan en EntidadCargo para no romper
    // código existente que lea propiedades del cargo directamente.
    // -------------------------------------------------------------------------

    public function getNombreCargo(): string
    {
        return $this->entidadCargo?->getNombreVisible() ?? '';
    }

    public function getAniosComputables(): float
    {
        return $this->entidadCargo?->getAniosComputables() ?? 0.0;
    }

    public function isComputaComoDirectivo(): bool
    {
        return $this->entidadCargo?->isComputaComoDirectivo() ?? false;
    }

    public function isEsRepresentativo(): bool
    {
        return $this->entidadCargo?->isEsRepresentativo() ?? false;
    }

    // -------------------------------------------------------------------------
    // Resto de propiedades propias
    // -------------------------------------------------------------------------

    public function isPrincipal(): bool
    {
        return $this->principal;
    }

    public function setPrincipal(bool $principal): static
    {
        $this->principal = $principal;

        return $this;
    }

    public function isComputaAntiguedad(): bool
    {
        return $this->computaAntiguedad;
    }

    public function setComputaAntiguedad(bool $computaAntiguedad): static
    {
        $this->computaAntiguedad = $computaAntiguedad;

        return $this;
    }

    public function isComputaReconocimiento(): bool
    {
        return $this->computaReconocimiento;
    }

    public function setComputaReconocimiento(bool $computaReconocimiento): static
    {
        $this->computaReconocimiento = $computaReconocimiento;

        return $this;
    }

    public function getAniosExtraAplicados(): float
    {
        return (float) $this->aniosExtraAplicados;
    }

    public function setAniosExtraAplicados(float $aniosExtraAplicados): static
    {
        $this->aniosExtraAplicados = number_format($aniosExtraAplicados, 2, '.', '');

        return $this;
    }

    public function getTipoPersona(): TipoPersonaEnum
    {
        return $this->tipoPersona;
    }

    public function setTipoPersona(TipoPersonaEnum $tipoPersona): static
    {
        $this->tipoPersona = $tipoPersona;

        return $this;
    }

    public function isInfantil(): bool
    {
        return $this->tipoPersona === TipoPersonaEnum::INFANTIL;
    }

    public function isCadete(): bool
    {
        return $this->tipoPersona === TipoPersonaEnum::CADETE;
    }

    public function isAdulto(): bool
    {
        return $this->tipoPersona === TipoPersonaEnum::ADULTO;
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

    public function getObservaciones(): ?string
    {
        return $this->observaciones;
    }

    public function setObservaciones(?string $observaciones): static
    {
        $this->observaciones = $observaciones;

        return $this;
    }
}
