<?php

namespace App\Repository;

use App\Entity\CensoEntrada;
use App\Entity\Entidad;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CensoEntrada>
 */
class CensoEntradaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CensoEntrada::class);
    }

    /**
     * @return CensoEntrada[]
     */
    public function findSinVincularByEntidad(Entidad $entidad): array
    {
        return $this->createQueryBuilder('ce')
            ->where('ce.entidad = :entidad')
            ->andWhere('ce.procesado = :procesado')
            ->setParameter('entidad', $entidad)
            ->setParameter('procesado', false)
            ->orderBy('ce.apellidos', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca coincidencia por email normalizado (case-insensitive, sin tildes)
     */
    public function findByEmailNormalizado(string $email, Entidad $entidad): ?CensoEntrada
    {
        $normalizedEmail = $this->normalizeEmail($email);

        return $this->createQueryBuilder('ce')
            ->where('ce.entidad = :entidad')
            ->andWhere('ce.procesado = :procesado')
            ->andWhere('LOWER(REPLACE(REPLACE(ce.email, \'á\', \'a\'), \'é\', \'e\')) LIKE :email')
            ->setParameter('entidad', $entidad)
            ->setParameter('procesado', false)
            ->setParameter('email', '%' . strtolower($normalizedEmail) . '%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Busca coincidencia por DNI (case-insensitive)
     */
    public function findByDni(string $dni, Entidad $entidad): ?CensoEntrada
    {
        return $this->createQueryBuilder('ce')
            ->where('ce.entidad = :entidad')
            ->andWhere('ce.procesado = :procesado')
            ->andWhere('UPPER(ce.dni) = :dni')
            ->setParameter('entidad', $entidad)
            ->setParameter('procesado', false)
            ->setParameter('dni', strtoupper($dni))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function normalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));
        // Remove plus addressing
        if (str_contains($email, '+')) {
            $email = preg_replace('/\+.+@/', '@', $email);
        }
        return $email;
    }
}
