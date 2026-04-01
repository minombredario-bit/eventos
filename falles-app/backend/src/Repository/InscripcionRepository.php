<?php

namespace App\Repository;

use App\Entity\Inscripcion;
use App\Entity\Evento;
use App\Entity\Usuario;
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
            ->setParameter('cancelada', \App\Enum\EstadoInscripcionEnum::CANCELADA)
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

    public function existeInscripcionPersonaEnEvento(int $personaId, Evento $evento): bool
    {
        $qb = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->join('i.lineas', 'l')
            ->where('i.evento = :evento')
            ->andWhere('l.persona = :persona')
            ->andWhere('i.estadoInscripcion != :cancelada')
            ->setParameter('evento', $evento)
            ->setParameter('persona', $personaId)
            ->setParameter('cancelada', \App\Enum\EstadoInscripcionEnum::CANCELADA);

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
            ->setParameter('cancelada', \App\Enum\EstadoInscripcionEnum::CANCELADA)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Verifica si una persona ya está inscrita en un evento.
     */
    public function personaYaInscrita(string $usuarioId, string $eventoId, string $personaId): bool
    {
        $result = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->join('i.usuario', 'u')
            ->join('i.evento', 'e')
            ->join('i.lineas', 'l')
            ->join('l.persona', 'p')
            ->where('u.id = :usuarioId')
            ->andWhere('e.id = :eventoId')
            ->andWhere('p.id = :personaId')
            ->andWhere('i.estadoInscripcion != :cancelada')
            ->setParameter('usuarioId', $usuarioId)
            ->setParameter('eventoId', $eventoId)
            ->setParameter('personaId', $personaId)
            ->setParameter('cancelada', \App\Enum\EstadoInscripcionEnum::CANCELADA)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result > 0;
    }

    public function personaYaInscritaEnFranja(
        string $usuarioId,
        string $eventoId,
        string $personaId,
        FranjaComidaEnum $franjaComida,
    ): bool {
        $result = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->join('i.usuario', 'u')
            ->join('i.evento', 'e')
            ->join('i.lineas', 'l')
            ->join('l.persona', 'p')
            ->join('l.menu', 'm')
            ->where('u.id = :usuarioId')
            ->andWhere('e.id = :eventoId')
            ->andWhere('p.id = :personaId')
            ->andWhere('m.franjaComida = :franjaComida')
            ->andWhere('i.estadoInscripcion != :cancelada')
            ->setParameter('usuarioId', $usuarioId)
            ->setParameter('eventoId', $eventoId)
            ->setParameter('personaId', $personaId)
            ->setParameter('franjaComida', $franjaComida)
            ->setParameter('cancelada', \App\Enum\EstadoInscripcionEnum::CANCELADA)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result > 0;
    }
}
