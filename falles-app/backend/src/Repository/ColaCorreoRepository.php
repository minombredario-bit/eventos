<?php

namespace App\Repository;

use App\Entity\ColaCorreo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ColaCorreo>
 */
class ColaCorreoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ColaCorreo::class);
    }

    /** @return ColaCorreo[] */
    public function findPendientes(int $limit = 50): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.estado = :estado')
            ->setParameter('estado', ColaCorreo::ESTADO_PENDIENTE)
            ->orderBy('c.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

