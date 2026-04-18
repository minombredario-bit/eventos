<?php

namespace App\Repository;

use App\Entity\Entidad;
use App\Entity\Reconocimiento;
use App\Entity\Usuario;
use App\Entity\UsuarioReconocimiento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UsuarioReconocimiento>
 */
class UsuarioReconocimientoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UsuarioReconocimiento::class);
    }

    /**
     * @return list<UsuarioReconocimiento>
     */
    public function findByUsuarioAndEntidad(Usuario|string $usuario, Entidad|string $entidad): array
    {
        return $this->createQueryBuilder('ur')
            ->leftJoin('ur.reconocimiento', 'r')->addSelect('r')
            ->andWhere('ur.usuario = :usuario')
            ->andWhere('ur.entidad = :entidad')
            ->setParameter('usuario', $usuario)
            ->setParameter('entidad', $entidad)
            ->orderBy('r.orden', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function existsUsuarioReconocimiento(
        Usuario|string $usuario,
        Reconocimiento|string $reconocimiento
    ): bool {
        $count = $this->createQueryBuilder('ur')
            ->select('COUNT(ur.id)')
            ->andWhere('ur.usuario = :usuario')
            ->andWhere('ur.reconocimiento = :reconocimiento')
            ->setParameter('usuario', $usuario)
            ->setParameter('reconocimiento', $reconocimiento)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    public function findUltimoReconocimientoDeUsuarioEnEntidad(
        Usuario|string $usuario,
        Entidad|string $entidad
    ): ?UsuarioReconocimiento {
        return $this->createQueryBuilder('ur')
            ->leftJoin('ur.reconocimiento', 'r')->addSelect('r')
            ->andWhere('ur.usuario = :usuario')
            ->andWhere('ur.entidad = :entidad')
            ->setParameter('usuario', $usuario)
            ->setParameter('entidad', $entidad)
            ->orderBy('r.orden', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
