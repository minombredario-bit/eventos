<?php

namespace App\Repository;

use App\Entity\PushSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PushSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushSubscription::class);
    }

    /**
     * Devuelve todas las suscripciones de un usuario concreto.
     *
     * @return PushSubscription[]
     */
    public function findByUsuarioId(string $usuarioId): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.usuarioId = :uid')
            ->setParameter('uid', $usuarioId)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Devuelve todas las suscripciones de una entidad.
     * Es el método principal para enviar notificaciones a todos los miembros,
     * ya que entidadId se guarda al registrarse y viene siempre del usuario autenticado.
     *
     * @return PushSubscription[]
     */
    public function findByEntidadId(string|int $entidadId): array
    {
        return $this->createQueryBuilder('ps')
            ->where('ps.entidadId = :entidadId')
            ->setParameter('entidadId', (string) $entidadId)
            ->getQuery()
            ->getResult();
    }

    public function findOneByEndpoint(string $endpoint): ?PushSubscription
    {
        $result = $this->findOneBy(['endpoint' => $endpoint]);

        return $result instanceof PushSubscription ? $result : null;
    }

    public function save(PushSubscription $pushSubscription, bool $flush = false): void
    {
        $this->getEntityManager()->persist($pushSubscription);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PushSubscription $pushSubscription, bool $flush = false): void
    {
        $this->getEntityManager()->remove($pushSubscription);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
