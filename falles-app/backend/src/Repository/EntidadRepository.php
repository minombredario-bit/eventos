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

    public function findByCodigoRegistro(string $codigo): ?Entidad
    {
        return $this->createQueryBuilder('e')
            ->where('e.codigoRegistro = :codigo')
            ->andWhere('e.activa = :activa')
            ->setParameter('codigo', strtoupper($codigo))
            ->setParameter('activa', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneBySlug(string $slug): ?Entidad
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
