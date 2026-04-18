<?php

namespace App\Repository;

use App\Entity\Entidad;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Entidad>
 */
class EntidadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entidad::class);
    }

    /**
     * @return list<Entidad>
     */
    public function findActivas(?string $tipoFiesta = null, ?string $subtipoFestero = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.activa = true')
            ->orderBy('e.nombre', 'ASC');

        if ($tipoFiesta !== null) {
            $qb->andWhere('e.tipoFiesta = :tipoFiesta')
                ->setParameter('tipoFiesta', $tipoFiesta);
        }

        if ($subtipoFestero !== null) {
            $qb->andWhere('e.subtipoFestero = :subtipoFestero')
                ->setParameter('subtipoFestero', $subtipoFestero);
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneBySlug(string $slug): ?Entidad
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.slug = :slug')
            ->setParameter('slug', $slug)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
