<?php

namespace App\Entity;

use App\Repository\PagoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use App\Enum\MetodoPagoEnum;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PagoRepository::class)]
#[ORM\Table(name: 'pago')]
#[ApiResource(
    normalizationContext: ['groups' => ['pago:read']],
    denormalizationContext: ['groups' => ['pago:write']],
    operations: [
        new Get(),
        new GetCollection(security: "is_granted('ROLE_ADMIN_ENTIDAD')"),
        new Post(security: "is_granted('ROLE_ADMIN_ENTIDAD')"),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'inscripcion' => 'exact',
    'inscripcion.id' => 'exact',
    'metodoPago' => 'exact',
    'estado' => 'exact',
    'registradoPor' => 'exact',
    'registradoPor.id' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['fecha', 'createdAt'])]
#[ApiFilter(
    OrderFilter::class,
    properties: ['fecha', 'createdAt', 'importe'],
    arguments: ['orderParameterName' => 'order']
)]
class Pago
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['pago:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Inscripcion::class, inversedBy: 'pagos')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['pago:read', 'pago:write'])]
    #[Assert\NotNull]
    private Inscripcion $inscripcion;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['pago:read', 'pago:write'])]
    private \DateTimeImmutable $fecha;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    #[Groups(['pago:read', 'pago:write'])]
    private string $importe;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: MetodoPagoEnum::class)]
    #[Groups(['pago:read', 'pago:write'])]
    #[Assert\NotNull]
    private MetodoPagoEnum $metodoPago;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['pago:read', 'pago:write'])]
    private ?string $referencia = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Groups(['pago:read'])]
    private string $estado = 'confirmado';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['pago:read', 'pago:write'])]
    private ?string $observaciones = null;

    #[ORM\ManyToOne(targetEntity: Usuario::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['pago:read'])]
    private Usuario $registradoPor;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['pago:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->createdAt = new \DateTimeImmutable();
        $this->fecha = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getInscripcion(): Inscripcion
    {
        return $this->inscripcion;
    }

    public function setInscripcion(Inscripcion $inscripcion): static
    {
        $this->inscripcion = $inscripcion;
        return $this;
    }

    public function getFecha(): \DateTimeImmutable
    {
        return $this->fecha;
    }

    public function setFecha(\DateTimeImmutable $fecha): static
    {
        $this->fecha = $fecha;
        return $this;
    }

    public function getImporte(): float
    {
        return (float) $this->importe;
    }

    public function setImporte(float $importe): static
    {
        $this->importe = (string) $importe;
        return $this;
    }

    public function getMetodoPago(): MetodoPagoEnum
    {
        return $this->metodoPago;
    }

    public function setMetodoPago(MetodoPagoEnum $metodoPago): static
    {
        $this->metodoPago = $metodoPago;
        return $this;
    }

    public function getReferencia(): ?string
    {
        return $this->referencia;
    }

    public function setReferencia(?string $referencia): static
    {
        $this->referencia = $referencia;
        return $this;
    }

    public function getEstado(): string
    {
        return $this->estado;
    }

    public function setEstado(string $estado): static
    {
        $this->estado = $estado;
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

    public function getRegistradoPor(): Usuario
    {
        return $this->registradoPor;
    }

    public function setRegistradoPor(Usuario $registradoPor): static
    {
        $this->registradoPor = $registradoPor;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
