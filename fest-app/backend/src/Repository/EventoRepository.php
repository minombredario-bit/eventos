<?php

namespace App\Repository;

use App\Entity\Evento;
use App\Entity\Entidad;
use App\Enum\EstadoEventoEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Evento>
 */
class EventoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evento::class);
    }

    /**
     * @return Evento[]
     */
    public function findPublicadosByEntidad(Entidad $entidad): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.entidad = :entidad')
            ->andWhere('e.publicado = :publicado')
            ->andWhere('e.visible = :visible')
            ->setParameter('entidad', $entidad)
            ->setParameter('publicado', true)
            ->setParameter('visible', true)
            ->orderBy('e.fechaEvento', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Evento[]
     */
    public function findAllByEntidad(Entidad $entidad): array
    {
        return $this->findBy(['entidad' => $entidad], ['fechaEvento' => 'DESC']);
    }

    /**
     * @return Evento[]
     */
    public function findForEstadoAutomation(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.estado != :cancelado')
            ->setParameter('cancelado', EstadoEventoEnum::CANCELADO)
            ->orderBy('e.fechaEvento', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
