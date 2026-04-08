<?php

namespace App\Repository;

use App\Entity\Evento;
use App\Entity\Invitado;
use App\Entity\RelacionUsuario;
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
        return $this->findByEventoAndHouseholdUsers($evento, $usuario);
    }

    /**
     * @return list<Invitado>
     */
    public function findByEventoAndHouseholdUsers(Evento $evento, Usuario $usuario): array
    {
        $householdUserIds = $this->resolveHouseholdUserIds($usuario);

        return $this->createQueryBuilder('i')
            ->where('i.evento = :evento')
            ->andWhere('IDENTITY(i.creadoPor) IN (:householdUserIds)')
            ->andWhere('i.deletedAt IS NULL')
            ->setParameter('evento', $evento)
            ->setParameter('householdUserIds', $householdUserIds)
            ->orderBy('i.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Invitado>
     */
    public function findActiveAllOrderedByCreatedAtDesc(): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.deletedAt IS NULL')
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveById(string $id): ?Invitado
    {
        return $this->createQueryBuilder('i')
            ->where('i.id = :id')
            ->andWhere('i.deletedAt IS NULL')
            ->setParameter('id', $id)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveByIdAndEventoAndUsuario(string $id, Evento $evento, Usuario $usuario): ?Invitado
    {
        return $this->findActiveByIdAndEventoAndHouseholdUsuario($id, $evento, $usuario);
    }

    public function findActiveByIdAndEventoAndHouseholdUsuario(string $id, Evento $evento, Usuario $usuario): ?Invitado
    {
        $householdUserIds = $this->resolveHouseholdUserIds($usuario);

        return $this->createQueryBuilder('i')
            ->where('i.id = :id')
            ->andWhere('i.evento = :evento')
            ->andWhere('IDENTITY(i.creadoPor) IN (:householdUserIds)')
            ->andWhere('i.deletedAt IS NULL')
            ->setParameter('id', $id)
            ->setParameter('evento', $evento)
            ->setParameter('householdUserIds', $householdUserIds)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function existsActiveByEventoAndHouseholdAndNormalizedName(
        Evento $evento,
        Usuario $usuario,
        string $normalizedFullName,
    ): bool {
        if ($normalizedFullName === '') {
            return false;
        }

        $householdUserIds = $this->resolveHouseholdUserIds($usuario);
        $invitados = $this->createQueryBuilder('i')
            ->where('i.evento = :evento')
            ->andWhere('IDENTITY(i.creadoPor) IN (:householdUserIds)')
            ->andWhere('i.deletedAt IS NULL')
            ->setParameter('evento', $evento)
            ->setParameter('householdUserIds', $householdUserIds)
            ->getQuery()
            ->getResult();

        foreach ($invitados as $invitado) {
            if (!$invitado instanceof Invitado) {
                continue;
            }

            if (self::normalizeName($invitado->getNombre(), $invitado->getApellidos()) === $normalizedFullName) {
                return true;
            }
        }

        return false;
    }

    public function findDeletedByEventoAndHouseholdAndNormalizedName(
        Evento $evento,
        Usuario $usuario,
        string $normalizedFullName,
    ): ?Invitado {
        if ($normalizedFullName === '') {
            return null;
        }

        $householdUserIds = $this->resolveHouseholdUserIds($usuario);
        $invitados = $this->createQueryBuilder('i')
            ->where('i.evento = :evento')
            ->andWhere('IDENTITY(i.creadoPor) IN (:householdUserIds)')
            ->andWhere('i.deletedAt IS NOT NULL')
            ->setParameter('evento', $evento)
            ->setParameter('householdUserIds', $householdUserIds)
            ->orderBy('i.deletedAt', 'DESC')
            ->addOrderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        foreach ($invitados as $invitado) {
            if (!$invitado instanceof Invitado) {
                continue;
            }

            if (self::normalizeName($invitado->getNombre(), $invitado->getApellidos()) === $normalizedFullName) {
                return $invitado;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function resolveHouseholdUserIds(Usuario $usuario): array
    {
        $usuarioId = (string) $usuario->getId();
        if ($usuarioId === '') {
            return [];
        }

        $rows = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('IDENTITY(r.usuarioOrigen) AS origenId', 'IDENTITY(r.usuarioDestino) AS destinoId')
            ->from(RelacionUsuario::class, 'r')
            ->where('IDENTITY(r.usuarioOrigen) = :usuarioId OR IDENTITY(r.usuarioDestino) = :usuarioId')
            ->setParameter('usuarioId', $usuarioId)
            ->getQuery()
            ->getArrayResult();

        $ids = [$usuarioId => true];

        foreach ($rows as $row) {
            $origenId = is_string($row['origenId'] ?? null) ? trim($row['origenId']) : '';
            $destinoId = is_string($row['destinoId'] ?? null) ? trim($row['destinoId']) : '';

            if ($origenId !== '') {
                $ids[$origenId] = true;
            }

            if ($destinoId !== '') {
                $ids[$destinoId] = true;
            }
        }

        return array_keys($ids);
    }

    public static function normalizeName(?string $nombre, ?string $apellidos): string
    {
        $fullName = trim(sprintf('%s %s', $nombre ?? '', $apellidos ?? ''));
        if ($fullName === '') {
            return '';
        }

        $collapsed = preg_replace('/\s+/u', ' ', $fullName) ?? $fullName;
        $normalized = mb_strtolower(trim($collapsed));

        return strtr($normalized, [
            'á' => 'a',
            'à' => 'a',
            'ä' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'å' => 'a',
            'é' => 'e',
            'è' => 'e',
            'ë' => 'e',
            'ê' => 'e',
            'í' => 'i',
            'ì' => 'i',
            'ï' => 'i',
            'î' => 'i',
            'ó' => 'o',
            'ò' => 'o',
            'ö' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ú' => 'u',
            'ù' => 'u',
            'ü' => 'u',
            'û' => 'u',
            'ñ' => 'n',
            'ç' => 'c',
        ]);
    }
}
