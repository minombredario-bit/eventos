<?php

namespace App\Entity;

use App\Repository\PasskeyCredentialRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity(repositoryClass: PasskeyCredentialRepository::class)]
#[ORM\Table(name: 'passkey_credential')]
#[ORM\HasLifecycleCallbacks]
class PasskeyCredential
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Usuario::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Usuario $usuario;

    /** Base64URL-encoded credential ID from the authenticator */
    #[ORM\Column(type: Types::TEXT, unique: true)]
    private string $credentialId;

    /** PEM-encoded public key (EC P-256 or RSA) */
    #[ORM\Column(type: Types::TEXT)]
    private string $publicKey;

    /** Current sign count for replay attack prevention */
    #[ORM\Column(type: Types::INTEGER)]
    private int $signCount = 0;

    /** User-friendly device name */
    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $deviceName = 'Dispositivo';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->toString();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string { return $this->id; }

    public function getUsuario(): Usuario { return $this->usuario; }
    public function setUsuario(Usuario $usuario): static { $this->usuario = $usuario; return $this; }

    public function getCredentialId(): string { return $this->credentialId; }
    public function setCredentialId(string $credentialId): static { $this->credentialId = $credentialId; return $this; }

    public function getPublicKey(): string { return $this->publicKey; }
    public function setPublicKey(string $publicKey): static { $this->publicKey = $publicKey; return $this; }

    public function getSignCount(): int { return $this->signCount; }
    public function setSignCount(int $signCount): static { $this->signCount = $signCount; return $this; }

    public function getDeviceName(): string { return $this->deviceName; }
    public function setDeviceName(string $deviceName): static { $this->deviceName = $deviceName; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getLastUsedAt(): ?\DateTimeImmutable { return $this->lastUsedAt; }
    public function setLastUsedAt(\DateTimeImmutable $lastUsedAt): static { $this->lastUsedAt = $lastUsedAt; return $this; }
}

