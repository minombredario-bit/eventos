<?php

namespace App\Repository;

use App\Entity\SeleccionParticipanteEvento;
use App\Entity\SeleccionParticipanteEventoLinea;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeleccionParticipanteEventoLinea>
 */
class SeleccionParticipanteEventoLineaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeleccionParticipanteEventoLinea::class);
    }

    /**
     * @return list<SeleccionParticipanteEventoLinea>
     */
    public function findBySeleccionParticipanteEvento(SeleccionParticipanteEvento $seleccionParticipanteEvento): array
    {
        return $this->findBy([
            'seleccionParticipanteEvento' => $seleccionParticipanteEvento,
        ], [
            'updatedAt' => 'DESC',
            'createdAt' => 'DESC',
        ]);
    }
}
