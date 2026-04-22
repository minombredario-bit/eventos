<?php
namespace App\Entity;

use App\Repository\AuditRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity(repositoryClass: AuditRepository::class)]
#[ORM\Table(name: 'audit')]
class Audit
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?string $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $entityType;

    #[ORM\Column(type: 'string', length: 255)]
    private string $entityId;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $actorId = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $action;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $changes = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function setEntityId(string $entityId): static
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getActorId(): ?string
    {
        return $this->actorId;
    }

    public function setActorId(?string $actorId): static
    {
        $this->actorId = $actorId;
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getChanges(): ?array
    {
        return $this->changes;
    }

    public function setChanges(?array $changes): static
    {
        $this->changes = $changes;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}

