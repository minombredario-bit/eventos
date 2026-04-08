<?php

namespace App\Repository;

use App\Entity\Usuario;
use App\Entity\Entidad;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Usuario>
 */
class UsuarioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Usuario::class);
    }

    public function findByEmail(string $email): ?Usuario
    {
        return $this->findOneBy(['email' => strtolower($email)]);
    }

    /**
     * @return Usuario[]
     */
    public function findPendientesByEntidad(Entidad $entidad): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.entidad = :entidad')
            ->andWhere('u.estadoValidacion = :estado')
            ->setParameter('entidad', $entidad)
            ->setParameter('estado', \App\Enum\EstadoValidacionEnum::PENDIENTE_VALIDACION)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Usuario[]
     */
    public function findByEntidad(Entidad $entidad): array
    {
        return $this->findBy(['entidad' => $entidad]);
    }
}
