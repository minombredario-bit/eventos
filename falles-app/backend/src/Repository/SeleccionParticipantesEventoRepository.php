<?php

namespace App\Repository;

use App\Entity\Evento;
use App\Entity\SeleccionParticipantesEvento;
use App\Entity\Usuario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeleccionParticipantesEvento>
 */
class SeleccionParticipantesEventoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeleccionParticipantesEvento::class);
    }

    public function findOneByUsuarioAndEvento(Usuario $usuario, Evento $evento): ?SeleccionParticipantesEvento
    {
        $selecciones = $this->findByUsuarioAndEventoOrdered($usuario, $evento);

        return $selecciones[0] ?? null;
    }

    /**
     * @return list<SeleccionParticipantesEvento>
     */
    public function findByUsuarioAndEventoOrdered(Usuario $usuario, Evento $evento): array
    {
        return $this->findBy([
            'usuario' => $usuario,
            'evento' => $evento,
        ], [
            'updatedAt' => 'DESC',
            'createdAt' => 'DESC',
        ]);
    }

    /**
     * @param list<string> $usuarioIds
     * @return list<SeleccionParticipantesEvento>
     */
    public function findByUsuarioIdsAndEvento(array $usuarioIds, Evento $evento): array
    {
        if ($usuarioIds === []) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->where('s.evento = :evento')
            ->andWhere('IDENTITY(s.usuario) IN (:usuarioIds)')
            ->setParameter('evento', $evento)
            ->setParameter('usuarioIds', $usuarioIds)
            ->orderBy('s.updatedAt', 'DESC')
            ->addOrderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
