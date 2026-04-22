<?php
namespace App\Repository;

use App\Entity\Audit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Audit>
 */
class AuditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Audit::class);
    }

    /**
     * Find audit records for a specific entity (type + id), ordered by newest first.
     * @return Audit[]
     */
    public function findByEntity(string $entityType, string $entityId, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.entityType = :type')
            ->andWhere('a.entityId = :id')
            ->setParameter('type', $entityType)
            ->setParameter('id', $entityId)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Convenience to find the latest audit entry for an entity, or null.
     */
    public function findLatestForEntity(string $entityType, string $entityId): ?Audit
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.entityType = :type')
            ->andWhere('a.entityId = :id')
            ->setParameter('type', $entityType)
            ->setParameter('id', $entityId)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

