<?php

namespace App\Repository;

use App\Entity\Entidad;
use App\Entity\Reconocimiento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reconocimiento>
 */
class ReconocimientoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reconocimiento::class);
    }

    /**
     * @return list<Reconocimiento>
     */
    public function findActivosByEntidad(Entidad|string $entidad): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.entidad = :entidad')
            ->andWhere('r.activo = true')
            ->setParameter('entidad', $entidad)
            ->orderBy('r.orden', 'ASC')
            ->addOrderBy('r.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByEntidadAndCodigo(Entidad|string $entidad, string $codigo): ?Reconocimiento
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.entidad = :entidad')
            ->andWhere('r.codigo = :codigo')
            ->setParameter('entidad', $entidad)
            ->setParameter('codigo', $codigo)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
