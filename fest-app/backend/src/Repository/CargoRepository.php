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

    /** @return Cargo[] */
    public function findByEntidad(Entidad $entidad): array
    {
        return $this->findBy(['entidad' => $entidad], ['nombre' => 'ASC']);
    }
}

