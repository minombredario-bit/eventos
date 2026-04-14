<?php

namespace App\Repository;

use App\Entity\Evento;
use App\Entity\Invitado;
use App\Entity\SeleccionParticipanteEvento;
use App\Entity\Usuario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeleccionParticipanteEvento>
 */
class SeleccionParticipanteEventoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeleccionParticipanteEvento::class);
    }

    /**
     * @return list<SeleccionParticipanteEvento>
     */
    public function findByEventoAndInscritoPorUsuario(Evento $evento, Usuario $usuario): array
    {
        return $this->findBy([
            'evento' => $evento,
            'inscritoPorUsuario' => $usuario,
        ], [
            'updatedAt' => 'DESC',
            'createdAt' => 'DESC',
        ]);
    }

    /**
     * @param list<string> $inscritoPorUsuarioIds
     * @return list<SeleccionParticipanteEvento>
     */
    public function findByEventoAndInscritoPorUsuarioIds(Evento $evento, array $inscritoPorUsuarioIds): array
    {
        if ($inscritoPorUsuarioIds === []) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->where('s.evento = :evento')
            ->andWhere('IDENTITY(s.inscritoPorUsuario) IN (:usuarioIds)')
            ->setParameter('evento', $evento)
            ->setParameter('usuarioIds', $inscritoPorUsuarioIds)
            ->orderBy('s.updatedAt', 'DESC')
            ->addOrderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<string> $inscritoPorUsuarioIds
     * @return list<SeleccionParticipanteEvento>
     */
    public function findByEventoAndInvitadoAndInscritoPorUsuarioIds(Evento $evento, Invitado $invitado, array $inscritoPorUsuarioIds): array
    {
        if ($inscritoPorUsuarioIds === []) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->where('s.evento = :evento')
            ->andWhere('s.invitado = :invitado')
            ->andWhere('IDENTITY(s.inscritoPorUsuario) IN (:usuarioIds)')
            ->setParameter('evento', $evento)
            ->setParameter('invitado', $invitado)
            ->setParameter('usuarioIds', $inscritoPorUsuarioIds)
            ->orderBy('s.updatedAt', 'DESC')
            ->addOrderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
