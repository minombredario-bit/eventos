<?php

namespace App\Repository;

use App\Entity\PersonaFamiliar;
use App\Entity\Usuario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PersonaFamiliar>
 */
class PersonaFamiliarRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PersonaFamiliar::class);
    }

    /**
     * @return PersonaFamiliar[]
     */
    public function findActivasByUsuario(Usuario $usuario): array
    {
        return $this->createQueryBuilder('pf')
            ->where('pf.usuarioPrincipal = :usuario')
            ->andWhere('pf.activa = :activa')
            ->setParameter('usuario', $usuario)
            ->setParameter('activa', true)
            ->orderBy('pf.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
