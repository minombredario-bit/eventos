<?php

namespace App\Repository;

use App\Entity\Evento;
use App\Entity\ActividadEvento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActividadEvento>
 */
class ActividadEventoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActividadEvento::class);
    }

    /**
     * @return ActividadEvento[]
     */
    public function findActivosByEvento(Evento $evento): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.evento = :evento')
            ->andWhere('m.activo = :activo')
            ->setParameter('evento', $evento)
            ->setParameter('activo', true)
            ->orderBy('m.ordenVisualizacion', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
