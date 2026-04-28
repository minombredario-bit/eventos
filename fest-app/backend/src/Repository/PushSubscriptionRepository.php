<?php

namespace App\Repository;

use App\Entity\PushSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PushSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushSubscription::class);
    }

    /**
     * @return PushSubscription[]
     */
    public function findByEntidadId(string|int $entidadId): array
    {
        return $this->createQueryBuilder('ps')
            ->andWhere('ps.entidadId = :entidadId')
            ->setParameter('entidadId', (string) $entidadId)
            ->getQuery()
            ->getResult();
    }

    public function findOneByEndpoint(string $endpoint): object
    {
        return $this->findOneBy([
            'endpoint' => $endpoint,
        ]);
    }

    public function save(PushSubscription $pushSubscription, bool $flush = false): void
    {
        $this->getEntityManager()->persist($pushSubscription);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PushSubscription $pushSubscription, bool $flush = false): void
    {
        $this->getEntityManager()->remove($pushSubscription);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
