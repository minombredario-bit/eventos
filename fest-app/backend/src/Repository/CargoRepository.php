<?php

namespace App\Repository;

use App\Entity\Cargo;
use App\Entity\Entidad;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cargo>
 */
class CargoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cargo::class);
    }

    /**
     * @return list<Cargo>
     */
    public function findByEntidad(
        Entidad|string $entidad,
        bool $soloActivos = true,
        ?bool $soloDirectivos = null,
        ?bool $soloRepresentativos = null
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.entidad = :entidad')
            ->setParameter('entidad', $entidad)
            ->orderBy('c.ordenJerarquico', 'ASC')
            ->addOrderBy('c.nombre', 'ASC');

        if ($soloActivos) {
            $qb->andWhere('c.activo = true');
        }

        if ($soloDirectivos !== null) {
            $qb->andWhere('c.computaComoDirectivo = :soloDirectivos')
                ->setParameter('soloDirectivos', $soloDirectivos);
        }

        if ($soloRepresentativos !== null) {
            $qb->andWhere('c.esRepresentativo = :soloRepresentativos')
                ->setParameter('soloRepresentativos', $soloRepresentativos);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<Cargo>
     */
    public function findDirectivosByEntidad(Entidad|string $entidad): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.entidad = :entidad')
            ->andWhere('c.activo = true')
            ->andWhere('c.computaComoDirectivo = true')
            ->setParameter('entidad', $entidad)
            ->orderBy('c.ordenJerarquico', 'ASC')
            ->addOrderBy('c.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByEntidadAndCodigo(Entidad|string $entidad, string $codigo): ?Cargo
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.entidad = :entidad')
            ->andWhere('c.codigo = :codigo')
            ->setParameter('entidad', $entidad)
            ->setParameter('codigo', $codigo)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByIdAndEntidad(
        Entidad|string $entidad,
        string $cargoId,
        bool $soloActivos = true
    ): ?Cargo {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.id = :cargoId')
            ->andWhere('c.entidad = :entidad')
            ->setParameter('cargoId', $cargoId)
            ->setParameter('entidad', $entidad)
            ->setMaxResults(1);

        if ($soloActivos) {
            $qb->andWhere('c.activo = true');
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findCargoPrincipalMasAlto(Entidad|string $entidad): ?Cargo
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.entidad = :entidad')
            ->andWhere('c.activo = true')
            ->setParameter('entidad', $entidad)
            ->orderBy('c.ordenJerarquico', 'ASC')
            ->addOrderBy('c.nombre', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
