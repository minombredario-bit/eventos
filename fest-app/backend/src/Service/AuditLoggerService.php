<?php
namespace App\Service;

use App\Entity\Audit;
use Doctrine\ORM\EntityManagerInterface;

final class AuditLoggerService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Create and persist an Audit entry. Does not flush (leave to caller / UnitOfWork).
     * @param string $entityType e.g. 'actividad_evento'
     * @param string $entityId
     * @param string $action e.g. 'delete'
     * @param array|null $changes arbitrary data that will be JSON encoded
     * @param string|null $actorId optional actor identifier
     * @param string|null $reason optional reason text
     */
    public function log(string $entityType, string $entityId, string $action, ?array $changes = null, ?string $actorId = null, ?string $reason = null): void
    {
        $audit = new Audit();
        $audit->setEntityType($entityType)
            ->setEntityId($entityId)
            ->setAction($action)
            ->setChanges($changes)
            ->setActorId($actorId)
            ->setReason($reason)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($audit);
    }
}

