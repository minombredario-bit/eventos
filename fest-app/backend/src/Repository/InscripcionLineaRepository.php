<?php

namespace App\Repository;

use App\Entity\ActividadEvento;
use App\Entity\InscripcionLinea;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InscripcionLinea>
 */
class InscripcionLineaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InscripcionLinea::class);
    }

    /**
     * Indica si existe al menos una línea pagada asociada a la actividad dada.
     */
    public function existePagadaForActividad(ActividadEvento $actividad): bool
    {
        return (bool) $this->createQueryBuilder('l')
            ->select('1')
            ->where('l.actividad = :actividad')
            ->andWhere('l.pagada = true')
            ->setParameter('actividad', $actividad->getId())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
