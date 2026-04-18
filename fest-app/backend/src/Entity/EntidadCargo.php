<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\EntidadCargoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_USER')"),
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_ADMIN_ENTIDAD')"),
        new Patch(security: "is_granted('ROLE_ADMIN_ENTIDAD')"),
    ],
    normalizationContext: ['groups' => ['entidad_cargo:read']],
    denormalizationContext: ['groups' => ['entidad_cargo:write']]
)]
#[ORM\Entity(repositoryClass: EntidadCargoRepository::class)]
#[ORM\Table(name: 'entidad_cargo')]
class EntidadCargo
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['entidad_cargo:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Entidad::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['entidad_cargo:read', 'entidad_cargo:write'])]
    #[Assert\NotNull]
    private ?Entidad $entidad = null;

    #[ORM\ManyToOne(targetEntity: CargoMaster::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups(['entidad_cargo:read', 'entidad_cargo:write'])]
    private ?CargoMaster $cargoMaster = null;

    #[ORM\ManyToOne(targetEntity: Cargo::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups(['entidad_cargo:read', 'entidad_cargo:write'])]
    private ?Cargo $cargo = null;

    #[ORM\Column(type: Types::STRING, length: 120, nullable: true)]
    #[Groups(['entidad_cargo:read', 'entidad_cargo:write'])]
    private ?string $nombre = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Groups(['entidad_cargo:read', 'entidad_cargo:write'])]
    private ?int $orden = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['entidad_cargo:read', 'entidad_cargo:write'])]
    private bool $activo = true;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->toString();
    }

    #[Assert\Callback]
    public function validateOrigen(ExecutionContextInterface $context): void
    {
        $hasCargoMaster = null !== $this->cargoMaster;
        $hasCargo = null !== $this->cargo;

        if ($hasCargoMaster === $hasCargo) {
            $context->buildViolation('Debes indicar exactamente uno entre cargoMaster o cargo.')
                ->atPath('cargoMaster')
                ->addViolation();
        }
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getEntidad(): ?Entidad
    {
        return $this->entidad;
    }

    public function setEntidad(?Entidad $entidad): static
    {
        $this->entidad = $entidad;

        return $this;
    }

    public function getCargoMaster(): ?CargoMaster
    {
        return $this->cargoMaster;
    }

    public function setCargoMaster(?CargoMaster $cargoMaster): static
    {
        $this->cargoMaster = $cargoMaster;

        return $this;
    }

    public function getCargo(): ?Cargo
    {
        return $this->cargo;
    }

    public function setCargo(?Cargo $cargo): static
    {
        $this->cargo = $cargo;

        return $this;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(?string $nombre): static
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getOrden(): ?int
    {
        return $this->orden;
    }

    public function setOrden(?int $orden): static
    {
        $this->orden = $orden;

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

    #[Groups(['entidad_cargo:read'])]
    public function isEsOficial(): bool
    {
        return null !== $this->cargoMaster;
    }

    #[Groups(['entidad_cargo:read'])]
    public function getNombreVisible(): ?string
    {
        if (null !== $this->nombre && '' !== trim($this->nombre)) {
            return $this->nombre;
        }

        return $this->cargo?->getNombre() ?? $this->cargoMaster?->getNombre();
    }

    #[Groups(['entidad_cargo:read'])]
    public function getCodigoVisible(): ?string
    {
        return $this->cargo?->getCodigo() ?? $this->cargoMaster?->getCodigo();
    }

    #[Groups(['entidad_cargo:read'])]
    public function getDescripcionVisible(): ?string
    {
        return $this->cargo?->getDescripcion() ?? $this->cargoMaster?->getDescripcion();
    }

    #[Groups(['entidad_cargo:read'])]
    public function isEsInfantil(): bool
    {
        if (null !== $this->cargo) {
            return $this->cargo->isInfantilEspecial();
        }

        if (null !== $this->cargoMaster) {
            // CargoMaster aún no tiene esInfantil/infantilEspecial en tu modelo actual
            return false;
        }

        return false;
    }

    #[Groups(['entidad_cargo:read'])]
    public function isInfantilEspecial(): bool
    {
        if (null !== $this->cargo) {
            return $this->cargo->isInfantilEspecial();
        }

        if (null !== $this->cargoMaster) {
            // CargoMaster aún no tiene este campo en tu modelo actual
            return false;
        }

        return false;
    }

    #[Groups(['entidad_cargo:read'])]
    public function isComputaComoDirectivo(): bool
    {
        if (null !== $this->cargo) {
            return $this->cargo->isComputaComoDirectivo();
        }

        if (null !== $this->cargoMaster && method_exists($this->cargoMaster, 'isComputaComoDirectivo')) {
            return $this->cargoMaster->isComputaComoDirectivo();
        }

        return false;
    }

    #[Groups(['entidad_cargo:read'])]
    public function isEsRepresentativo(): bool
    {
        if (null !== $this->cargo) {
            return $this->cargo->isEsRepresentativo();
        }

        if (null !== $this->cargoMaster && method_exists($this->cargoMaster, 'isEsRepresentativo')) {
            return $this->cargoMaster->isEsRepresentativo();
        }

        return false;
    }

    #[Groups(['entidad_cargo:read'])]
    public function getOrdenJerarquicoVisible(): int
    {
        if (null !== $this->orden) {
            return $this->orden;
        }

        if (null !== $this->cargo) {
            return $this->cargo->getOrdenJerarquico();
        }

        if (null !== $this->cargoMaster && method_exists($this->cargoMaster, 'getOrdenJerarquico')) {
            return $this->cargoMaster->getOrdenJerarquico();
        }

        return 0;
    }

    #[Groups(['entidad_cargo:read'])]
    public function getAniosComputables(): float
    {
        if (null !== $this->cargoMaster) {
            if (method_exists($this->cargoMaster, 'getAniosComputables')) {
                return (float) $this->cargoMaster->getAniosComputables();
            }

            // Cuando completes CargoMaster con el campo oficial, esto ya no hará falta.
            return 0.0;
        }

        if (null !== $this->cargo) {
            // Regla de negocio: los cargos creados siempre computan 1.
            return 1.0;
        }

        return 0.0;
    }
}
