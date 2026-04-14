<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\CargoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CargoRepository::class)]
#[ORM\Table(name: 'cargo')]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_USER')"),
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_ADMIN_ENTIDAD')"),
        new Patch(security: "is_granted('ROLE_ADMIN_ENTIDAD')"),
    ],
    normalizationContext: ['groups' => ['cargo:read']],
    denormalizationContext: ['groups' => ['cargo:write']]
)]
class Cargo
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['cargo:read', 'usuario:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Entidad::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['cargo:read', 'cargo:write'])]
    #[Assert\NotNull]
    private Entidad $entidad;

    #[ORM\Column(type: Types::STRING, length: 120)]
    #[Groups(['cargo:read', 'cargo:write', 'usuario:read'])]
    #[Assert\NotBlank]
    private string $nombre;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['cargo:read', 'cargo:write'])]
    private ?string $descripcion = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    #[Groups(['cargo:read', 'cargo:write'])]
    private string $multiplicador = '1.00';

    /** @var Collection<int, Usuario> */
    #[ORM\ManyToMany(targetEntity: Usuario::class, mappedBy: 'cargos')]
    private Collection $usuarios;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->usuarios = new ArrayCollection();
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

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static
    {
        $this->descripcion = $descripcion;

        return $this;
    }

    public function getMultiplicador(): float
    {
        return (float) $this->multiplicador;
    }

    public function setMultiplicador(float $multiplicador): static
    {
        $this->multiplicador = (string) $multiplicador;

        return $this;
    }

    /** @return Collection<int, Usuario> */
    public function getUsuarios(): Collection
    {
        return $this->usuarios;
    }
}

