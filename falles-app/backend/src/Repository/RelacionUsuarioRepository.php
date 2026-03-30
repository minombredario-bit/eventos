<?php

namespace App\Repository;

use App\Entity\RelacionUsuario;
use App\Entity\Usuario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RelacionUsuarioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RelacionUsuario::class);
    }

    public function findRelacionadosByUsuario(Usuario $usuario): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        $qb->select('r')
            ->from(RelacionUsuario::class, 'r')
            ->where('r.usuarioOrigen = :usuario OR r.usuarioDestino = :usuario')
            ->setParameter('usuario', $usuario->getId());

        return $qb->getQuery()->getResult();
    }
}
