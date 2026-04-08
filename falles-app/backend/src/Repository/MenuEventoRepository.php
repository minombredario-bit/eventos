<?php

namespace App\Repository;

use App\Entity\MenuEvento;
use App\Entity\Evento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MenuEvento>
 */
class MenuEventoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuEvento::class);
    }

    /**
     * @return MenuEvento[]
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
