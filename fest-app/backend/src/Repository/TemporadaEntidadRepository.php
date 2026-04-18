<?php

namespace App\Repository;

use App\Entity\Entidad;
use App\Entity\TemporadaEntidad;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TemporadaEntidad>
 */
class TemporadaEntidadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TemporadaEntidad::class);
    }

    /**
     * @return list<TemporadaEntidad>
     */
    public function findByEntidad(Entidad|string $entidad, bool $soloAbiertas = false): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.entidad = :entidad')
            ->setParameter('entidad', $entidad)
            ->orderBy('t.codigo', 'DESC');

        if ($soloAbiertas) {
            $qb->andWhere('t.cerrada = false');
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneByEntidadAndCodigo(Entidad|string $entidad, string $codigo): ?TemporadaEntidad
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.entidad = :entidad')
            ->andWhere('t.codigo = :codigo')
            ->setParameter('entidad', $entidad)
            ->setParameter('codigo', $codigo)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findTemporadaActualDeEntidad(Entidad $entidad): ?TemporadaEntidad
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.entidad = :entidad')
            ->andWhere('t.codigo = :codigo')
            ->setParameter('entidad', $entidad)
            ->setParameter('codigo', $entidad->getTemporadaActual())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findUltimaTemporadaDeEntidad(Entidad|string $entidad): ?TemporadaEntidad
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.entidad = :entidad')
            ->setParameter('entidad', $entidad)
            ->orderBy('t.codigo', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
