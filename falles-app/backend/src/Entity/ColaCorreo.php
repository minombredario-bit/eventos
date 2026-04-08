<?php

namespace App\Entity;

use App\Repository\ColaCorreoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity(repositoryClass: ColaCorreoRepository::class)]
#[ORM\Table(name: 'cola_correo')]
#[ORM\HasLifecycleCallbacks]
class ColaCorreo
{
    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_ENVIADO = 'enviado';
    public const ESTADO_ERROR = 'error';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Entidad::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Entidad $entidad = null;

    #[ORM\ManyToOne(targetEntity: Usuario::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Usuario $usuario = null;

    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $destinatario;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $asunto;

    #[ORM\Column(type: Types::STRING, length: 120)]
    private string $plantilla;

    #[ORM\Column(type: Types::JSON)]
    private array $contexto = [];

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $estado = self::ESTADO_PENDIENTE;

    #[ORM\Column(type: Types::INTEGER)]
    private int $intentos = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $ultimoError = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $enviadoAt = null;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getUsuario(): ?Usuario
    {
        return $this->usuario;
    }

    public function setUsuario(?Usuario $usuario): static
    {
        $this->usuario = $usuario;

        return $this;
    }

    public function getDestinatario(): string
    {
        return $this->destinatario;
    }

    public function setDestinatario(string $destinatario): static
    {
        $this->destinatario = strtolower(trim($destinatario));

        return $this;
    }

    public function getAsunto(): string
    {
        return $this->asunto;
    }

    public function setAsunto(string $asunto): static
    {
        $this->asunto = $asunto;

        return $this;
    }

    public function getPlantilla(): string
    {
        return $this->plantilla;
    }

    public function setPlantilla(string $plantilla): static
    {
        $this->plantilla = $plantilla;

        return $this;
    }

    public function getContexto(): array
    {
        return $this->contexto;
    }

    public function setContexto(array $contexto): static
    {
        $this->contexto = $contexto;

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

    public function getIntentos(): int
    {
        return $this->intentos;
    }

    public function incrementarIntentos(): static
    {
        $this->intentos++;

        return $this;
    }

    public function getUltimoError(): ?string
    {
        return $this->ultimoError;
    }

    public function setUltimoError(?string $ultimoError): static
    {
        $this->ultimoError = $ultimoError;

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

    public function getEnviadoAt(): ?\DateTimeImmutable
    {
        return $this->enviadoAt;
    }

    public function setEnviadoAt(?\DateTimeImmutable $enviadoAt): static
    {
        $this->enviadoAt = $enviadoAt;

        return $this;
    }
}

