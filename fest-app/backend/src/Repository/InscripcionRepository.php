<?php

namespace App\Repository;

use App\Entity\Inscripcion;
use App\Entity\Evento;
use App\Entity\Usuario;
use App\Enum\EstadoInscripcionEnum;
use App\Enum\EstadoLineaInscripcionEnum;
use App\Enum\FranjaComidaEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Inscripcion>
 */
class InscripcionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Inscripcion::class);
    }

    /**
     * @return Inscripcion[]
     */
    public function findByUsuario(Usuario $usuario): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.usuario = :usuario')
            ->setParameter('usuario', $usuario)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Inscripcion[]
     */
    public function findByEvento(Evento $evento): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.evento = :evento')
            ->setParameter('evento', $evento)
            ->orderBy('i.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Inscripcion[]
     */
    public function findApuntadosByEvento(Evento $evento, ?string $search = null): array
    {
        $queryBuilder = $this->createQueryBuilder('i')
            ->innerJoin('i.usuario', 'u')
            ->addSelect('u')
            ->leftJoin('i.lineas', 'l', 'WITH', 'l.estadoLinea != :lineaCancelada')
            ->addSelect('l')
            ->where('i.evento = :evento')
            ->andWhere('i.estadoInscripcion != :cancelada')
            ->setParameter('evento', $evento)
            ->setParameter('cancelada', EstadoInscripcionEnum::CANCELADA)
            ->setParameter('lineaCancelada', EstadoLineaInscripcionEnum::CANCELADA)
            ->orderBy('u.nombre', 'ASC')
            ->addOrderBy('u.apellidos', 'ASC');

        $normalizedSearch = trim((string) $search);
        if ($normalizedSearch !== '') {
            $likeSearch = '%' . mb_strtolower($normalizedSearch) . '%';

            $queryBuilder
                ->andWhere('LOWER(CONCAT(u.nombre, :space, u.apellidos)) LIKE :search OR LOWER(u.nombre) LIKE :search OR LOWER(u.apellidos) LIKE :search')
                ->setParameter('space', ' ')
                ->setParameter('search', $likeSearch);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function existeInscripcionUsuarioEnEvento(string $usuarioParticipanteId, Evento $evento): bool
    {
        $qb = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->join('i.lineas', 'l')
            ->where('i.evento = :evento')
            ->andWhere('l.usuario = :usuarioParticipante')
            ->andWhere('i.estadoInscripcion != :cancelada')
            ->setParameter('evento', $evento)
            ->setParameter('usuarioParticipante', $usuarioParticipanteId)
            ->setParameter('cancelada', EstadoInscripcionEnum::CANCELADA);

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Busca inscripción por usuario y evento (para validar que no haya duplicado).
     */
    public function findOneByUsuarioAndEvento(string $usuarioId, string $eventoId): ?Inscripcion
    {
        return $this->createQueryBuilder('i')
            ->join('i.usuario', 'u')
            ->join('i.evento', 'e')
            ->where('u.id = :usuarioId')
            ->andWhere('e.id = :eventoId')
            ->andWhere('i.estadoInscripcion != :cancelada')
            ->setParameter('usuarioId', $usuarioId)
            ->setParameter('eventoId', $eventoId)
            ->setParameter('cancelada', EstadoInscripcionEnum::CANCELADA)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Busca inscripción activa por invitado y evento.
     */
    public function findOneByInvitadoAndEvento(string $invitadoId, string $eventoId): ?Inscripcion
    {
        return $this->createQueryBuilder('i')
            ->join('i.evento', 'e')
            ->join('i.lineas', 'l')
            ->join('l.invitado', 'inv')
            ->where('inv.id = :invitadoId')
            ->andWhere('e.id = :eventoId')
            ->andWhere('i.estadoInscripcion != :cancelada')
            ->setParameter('invitadoId', $invitadoId)
            ->setParameter('eventoId', $eventoId)
            ->setParameter('cancelada', EstadoInscripcionEnum::CANCELADA)
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Verifica si una persona ya está inscrita en un evento.
     */
    public function usuarioYaInscrito(string $usuarioId, string $eventoId, string $usuarioParticipanteId): bool
    {
        $result = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->join('i.usuario', 'u')
            ->join('i.evento', 'e')
            ->join('i.lineas', 'l')
            ->join('l.usuario', 'up')
            ->where('u.id = :usuarioId')
            ->andWhere('e.id = :eventoId')
            ->andWhere('up.id = :usuarioParticipanteId')
            ->andWhere('i.estadoInscripcion != :cancelada')
            ->setParameter('usuarioId', $usuarioId)
            ->setParameter('eventoId', $eventoId)
            ->setParameter('usuarioParticipanteId', $usuarioParticipanteId)
            ->setParameter('cancelada', EstadoInscripcionEnum::CANCELADA)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result > 0;
    }

    public function usuarioYaInscritoEnFranja(
        string $usuarioId,
        string $eventoId,
        string $usuarioParticipanteId,
        FranjaComidaEnum $franjaComida,
    ): bool {
        $result = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->join('i.usuario', 'u')
            ->join('i.evento', 'e')
            ->join('i.lineas', 'l')
            ->join('l.usuario', 'up')
            ->join('l.actividad', 'm')
            ->where('u.id = :usuarioId')
            ->andWhere('e.id = :eventoId')
            ->andWhere('up.id = :usuarioParticipanteId')
            ->andWhere('m.franjaComida = :franjaComida')
            ->andWhere('l.estadoLinea != :lineaCancelada')
            ->andWhere('i.estadoInscripcion != :cancelada')
            ->setParameter('usuarioId', $usuarioId)
            ->setParameter('eventoId', $eventoId)
            ->setParameter('usuarioParticipanteId', $usuarioParticipanteId)
            ->setParameter('franjaComida', $franjaComida)
            ->setParameter('lineaCancelada', EstadoLineaInscripcionEnum::CANCELADA)
            ->setParameter('cancelada', EstadoInscripcionEnum::CANCELADA)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result > 0;
    }

    public function invitadoYaInscritoEnFranja(
        string $usuarioId,
        string $eventoId,
        string $invitadoId,
        FranjaComidaEnum $franjaComida,
    ): bool {
        $result = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->join('i.usuario', 'u')
            ->join('i.evento', 'e')
            ->join('i.lineas', 'l')
            ->join('l.invitado', 'inv')
            ->join('l.actividad', 'm')
            ->where('u.id = :usuarioId')
            ->andWhere('e.id = :eventoId')
            ->andWhere('inv.id = :invitadoId')
            ->andWhere('m.franjaComida = :franjaComida')
            ->andWhere('l.estadoLinea != :lineaCancelada')
            ->andWhere('i.estadoInscripcion != :cancelada')
            ->setParameter('usuarioId', $usuarioId)
            ->setParameter('eventoId', $eventoId)
            ->setParameter('invitadoId', $invitadoId)
            ->setParameter('franjaComida', $franjaComida)
            ->setParameter('lineaCancelada', EstadoLineaInscripcionEnum::CANCELADA)
            ->setParameter('cancelada', EstadoInscripcionEnum::CANCELADA)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result > 0;
    }
}
