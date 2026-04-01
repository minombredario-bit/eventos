<?php

namespace App\Repository;

use App\Entity\Evento;
use App\Entity\Invitado;
use App\Entity\Usuario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invitado>
 */
class InvitadoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invitado::class);
    }

    /**
     * @return Invitado[]
     */
    public function findByEventoAndUsuario(Evento $evento, Usuario $usuario): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.evento = :evento')
            ->andWhere('i.creadoPor = :usuario')
            ->setParameter('evento', $evento)
            ->setParameter('usuario', $usuario)
            ->orderBy('i.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
