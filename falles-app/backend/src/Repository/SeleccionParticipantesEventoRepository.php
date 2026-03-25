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
        return $this->findOneBy([
            'usuario' => $usuario,
            'evento' => $evento,
        ]);
    }
}
