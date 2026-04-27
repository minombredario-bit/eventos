<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use App\Repository\EntidadCargoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Punto de asignación uniforme de cargos a una entidad.
 *
 * Toda asignación de cargo a un usuario pasa por esta entidad,
 * independientemente de si el cargo es oficial (CargoMaster) o interno (Cargo).
 * Esto garantiza que UsuarioTemporadaCargo siempre apunte a un EntidadCargo,
 * nunca directamente a Cargo o CargoMaster.
 *
 * Invariante: exactamente uno entre $cargoMaster y $cargo debe ser no nulo.
 */
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_ADMIN_ENTIDAD')"),
        new GetCollection(security: "is_granted('ROLE_ADMIN_ENTIDAD')"),
        new Post(security: "is_granted('ROLE_ADMIN_ENTIDAD')"),
        new Patch(security: "is_granted('ROLE_ADMIN_ENTIDAD')"),
        new Delete(security: "is_granted('ROLE_ADMIN_ENTIDAD') or is_granted('ROLE_SUPERADMIN')"),
    ],
    normalizationContext: ['groups' => ['entidad_cargo:read']],
    denormalizationContext: ['groups' => ['entidad_cargo:write']]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'entidad'              => 'exact',
    'cargo'                => 'exact',
    'cargoMaster'          => 'exact',
    'cargo.nombre'         => 'partial',
    'cargo.codigo'         => 'partial',
    'cargoMaster.nombre'   => 'partial',
    'cargoMaster.codigo'   => 'partial',
])]
#[ApiFilter(BooleanFilter::class, properties: [
    'activo',
    'cargo.esInfantil',
    'cargo.infantilEspecial',
    'cargoMaster.esInfantil',
    'cargoMaster.infantilEspecial',
])]
#[ApiFilter(ExistsFilter::class, properties: [
    'cargo',
    'cargoMaster',
])]
#[ApiFilter(OrderFilter::class, properties: [
    'orden',
    'cargo.nombre',
    'cargo.codigo',
    'cargo.ordenJerarquico',
    'cargoMaster.nombre',
    'cargoMaster.codigo',
    'cargoMaster.ordenJerarquico',
])]
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
    #[Groups(['entidad_cargo:write'])]
    #[Assert\NotNull]
    private ?Entidad $entidad = null;

    /**
     * Cargo oficial definido en el catálogo global (gestionado por superadmin).
     * Mutuamente excluyente con $cargo.
     */
    #[ORM\ManyToOne(targetEntity: CargoMaster::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups(['entidad_cargo:read', 'entidad_cargo:write'])]
    private ?CargoMaster $cargoMaster = null;

    /**
     * Cargo interno creado por la propia entidad.
     * Mutuamente excluyente con $cargoMaster.
     */
    #[ORM\ManyToOne(targetEntity: Cargo::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups(['entidad_cargo:read', 'entidad_cargo:write'])]
    private ?Cargo $cargo = null;

    /**
     * Nombre personalizado para este cargo en el contexto de la entidad.
     * Si es null, se usa el nombre del cargo subyacente.
     */
    #[ORM\Column(type: Types::STRING, length: 120, nullable: true)]
    #[Groups(['entidad_cargo:read', 'entidad_cargo:write'])]
    private ?string $nombre = null;

    /**
     * Orden de visualización personalizado para esta entidad.
     * Si es null, se usa el ordenJerarquico del cargo subyacente.
     */
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

    // -------------------------------------------------------------------------
    // Validación de invariante XOR
    // -------------------------------------------------------------------------

    #[Assert\Callback]
    public function validateOrigen(ExecutionContextInterface $context): void
    {
        $hasCargoMaster = null !== $this->cargoMaster;
        $hasCargo       = null !== $this->cargo;

        if ($hasCargoMaster === $hasCargo) {
            $context->buildViolation('Debes indicar exactamente uno entre cargoMaster o cargo.')
                ->atPath('cargoMaster')
                ->addViolation();
        }

        // Business rule: for TipoEntidad 'falla' only a restricted set of CargoMaster codes are allowed.
        // If entidad is set and its tipoEntidad code equals 'falla', enforce allowed cargoMaster codes.
        if ($hasCargoMaster && $this->entidad?->getTipoEntidad()?->getCodigo() === 'falla') {
            $allowed = [
                'DELEGADO_FESTEJOS', 'PRESIDENTE', 'PRESIDENTE_INFANTIL', 'VICESECRETARIO',
                'DELEGADO_PROTOCOLO', 'FALLERA_MAYOR_INFANTIL', 'VICEPRESIDENTE_1', 'TESORERO',
                'VICEPRESIDENTE_2', 'DELEGADO_CULTURA', 'FALLERA_MAYOR', 'DELEGADO_INFANTILES',
                'SECRETARIO', 'ABANDERADO_INFANTIL'
            ];

            $codigo = $this->cargoMaster?->getCodigo();
            if ($codigo === null || !in_array($codigo, $allowed, true)) {
                $context->buildViolation('Para tipo entidad "falla" sólo están permitidos cargos oficiales: ' . implode(', ', $allowed))
                    ->atPath('cargoMaster')
                    ->addViolation();
            }
        }
    }

    // -------------------------------------------------------------------------
    // Getters y setters de propiedades persistidas
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Métodos de clasificación
    // -------------------------------------------------------------------------

    /**
     * Indica si el cargo es oficial (viene del catálogo global) o interno.
     */
    #[Groups(['entidad_cargo:read'])]
    public function isEsOficial(): bool
    {
        return null !== $this->cargoMaster;
    }

    // -------------------------------------------------------------------------
    // Getters virtuales: delegan al cargo subyacente sin bifurcación defensiva
    // -------------------------------------------------------------------------

    /**
     * Nombre visible: usa el override de la entidad si existe,
     * o el nombre del cargo subyacente en caso contrario.
     */
    #[Groups(['entidad_cargo:read'])]
    public function getNombreVisible(): string
    {
        if (null !== $this->nombre && '' !== trim($this->nombre)) {
            return $this->nombre;
        }

        return $this->cargoMaster?->getNombre() ?? $this->cargo->getNombre();
    }

    #[Groups(['entidad_cargo:read'])]
    public function getCodigoVisible(): ?string
    {
        return $this->cargoMaster?->getCodigo() ?? $this->cargo?->getCodigo();
    }

    #[Groups(['entidad_cargo:read'])]
    public function getDescripcionVisible(): ?string
    {
        return $this->cargoMaster?->getDescripcion() ?? $this->cargo?->getDescripcion();
    }

    #[Groups(['entidad_cargo:read'])]
    public function isComputaComoDirectivo(): bool
    {
        return $this->cargoMaster?->isComputaComoDirectivo()
            ?? $this->cargo?->isComputaComoDirectivo()
            ?? false;
    }

    #[Groups(['entidad_cargo:read'])]
    public function isEsRepresentativo(): bool
    {
        return $this->cargoMaster?->isEsRepresentativo()
            ?? $this->cargo?->isEsRepresentativo()
            ?? false;
    }

    #[Groups(['entidad_cargo:read'])]
    public function isEsInfantil(): bool
    {
        return $this->cargoMaster?->isEsInfantil()
            ?? $this->cargo?->isEsInfantil()
            ?? false;
    }

    #[Groups(['entidad_cargo:read'])]
    public function isInfantilEspecial(): bool
    {
        return $this->cargoMaster?->isInfantilEspecial()
            ?? $this->cargo?->isInfantilEspecial()
            ?? false;
    }

    /**
     * Orden jerárquico: usa el override de la entidad si existe,
     * o el orden del cargo subyacente en caso contrario.
     */
    #[Groups(['entidad_cargo:read'])]
    public function getOrdenJerarquicoVisible(): int
    {
        if (null !== $this->orden) {
            return $this->orden;
        }

        return $this->cargoMaster?->getOrdenJerarquico()
            ?? $this->cargo?->getOrdenJerarquico()
            ?? 0;
    }

    /**
     * Años computables: los cargos oficiales usan el valor configurado en CargoMaster,
     * los internos siempre computan 1.0 por regla de negocio.
     */
    #[Groups(['entidad_cargo:read'])]
    public function getAniosComputables(): float
    {
        return $this->cargoMaster?->getAniosComputables()
            ?? $this->cargo?->getAniosComputables()
            ?? 0.0;
    }
}
