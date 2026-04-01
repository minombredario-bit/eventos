<?php

namespace App\Entity;

use App\Repository\CensoEntradaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use App\Enum\TipoPersonaEnum;
use App\Enum\TipoRelacionEconomicaEnum;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CensoEntradaRepository::class)]
#[ORM\Table(name: 'censo_entrada')]
#[ApiResource(
    normalizationContext: ['groups' => ['censo-entrada:read']],
    denormalizationContext: ['groups' => ['censo-entrada:write']],
    operations: [
        new Get(security: "is_granted('ROLE_ADMIN_ENTIDAD')"),
        new GetCollection(security: "is_granted('ROLE_ADMIN_ENTIDAD')"),
        new Patch(security: "is_granted('ROLE_SUPERADMIN')"),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'entidad' => 'exact',
    'entidad.id' => 'exact',
    'temporada' => 'exact',
    'tipoPersona' => 'exact',
    'tipoRelacionEconomica' => 'exact',
    'nombre' => 'partial',
    'apellidos' => 'partial',
    'email' => 'partial',
    'dni' => 'partial',
])]
#[ApiFilter(BooleanFilter::class, properties: ['procesado'])]
#[ApiFilter(
    OrderFilter::class,
    properties: ['createdAt', 'apellidos', 'nombre'],
    arguments: ['orderParameterName' => 'order']
)]
class CensoEntrada
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['censo-entrada:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Entidad::class, inversedBy: 'censoEntradas')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['censo-entrada:read', 'censo-entrada:write'])]
    #[Assert\NotNull]
    private Entidad $entidad;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Groups(['censo-entrada:read', 'censo-entrada:write'])]
    #[Assert\NotBlank]
    private string $nombre;

    #[ORM\Column(type: Types::STRING, length: 150)]
    #[Groups(['censo-entrada:read', 'censo-entrada:write'])]
    #[Assert\NotBlank]
    private string $apellidos;

    #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
    #[Groups(['censo-entrada:read', 'censo-entrada:write'])]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Groups(['censo-entrada:read', 'censo-entrada:write'])]
    private ?string $dni = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Groups(['censo-entrada:read', 'censo-entrada:write'])]
    private string $parentesco = 'otro';

    #[ORM\Column(type: Types::STRING, length: 50, enumType: TipoPersonaEnum::class)]
    #[Groups(['censo-entrada:read', 'censo-entrada:write'])]
    private TipoPersonaEnum $tipoPersona;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: TipoRelacionEconomicaEnum::class)]
    #[Groups(['censo-entrada:read', 'censo-entrada:write'])]
    private TipoRelacionEconomicaEnum $tipoRelacionEconomica;

    #[ORM\Column(type: Types::STRING, length: 10)]
    #[Groups(['censo-entrada:read', 'censo-entrada:write'])]
    private string $temporada;

    #[ORM\ManyToOne(targetEntity: Usuario::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['censo-entrada:read'])]
    private ?Usuario $usuarioVinculado = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['censo-entrada:read'])]
    private bool $procesado = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['censo-entrada:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->createdAt = new \DateTimeImmutable();
        $this->tipoPersona = TipoPersonaEnum::ADULTO;
        $this->tipoRelacionEconomica = TipoRelacionEconomicaEnum::INTERNO;
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

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;
        return $this;
    }

    public function getApellidos(): string
    {
        return $this->apellidos;
    }

    public function setApellidos(string $apellidos): static
    {
        $this->apellidos = $apellidos;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email !== null ? strtolower(trim($email)) : null;
        return $this;
    }

    public function getDni(): ?string
    {
        return $this->dni;
    }

    public function setDni(?string $dni): static
    {
        $this->dni = $dni !== null ? strtoupper(trim($dni)) : null;
        return $this;
    }

    public function getParentesco(): string
    {
        return $this->parentesco;
    }

    public function setParentesco(string $parentesco): static
    {
        $this->parentesco = $parentesco;
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

    public function getTipoRelacionEconomica(): TipoRelacionEconomicaEnum
    {
        return $this->tipoRelacionEconomica;
    }

    public function setTipoRelacionEconomica(TipoRelacionEconomicaEnum $tipoRelacionEconomica): static
    {
        $this->tipoRelacionEconomica = $tipoRelacionEconomica;
        return $this;
    }

    public function getTemporada(): string
    {
        return $this->temporada;
    }

    public function setTemporada(string $temporada): static
    {
        $this->temporada = $temporada;
        return $this;
    }

    public function getUsuarioVinculado(): ?Usuario
    {
        return $this->usuarioVinculado;
    }

    public function setUsuarioVinculado(?Usuario $usuarioVinculado): static
    {
        $this->usuarioVinculado = $usuarioVinculado;
        return $this;
    }

    public function isProcesado(): bool
    {
        return $this->procesado;
    }

    public function setProcesado(bool $procesado): static
    {
        $this->procesado = $procesado;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getNombreCompleto(): string
    {
        return sprintf('%s %s', $this->nombre, $this->apellidos);
    }

    /**
     * Normalizes email for matching (removes dots, plus addressing, etc.)
     */
    public function getEmailNormalizado(): ?string
    {
        if ($this->email === null) {
            return null;
        }
        $email = strtolower($this->email);
        // Remove dots from Gmail addresses (before @)
        if (str_contains($email, '@gmail.com')) {
            $email = explode('@', $email)[0];
            $email = str_replace('.', '', $email) . '@gmail.com';
        }
        // Remove plus addressing
        if (str_contains($email, '+')) {
            $email = preg_replace('/\+.+@/', '@', $email);
        }
        return $email;
    }
}
