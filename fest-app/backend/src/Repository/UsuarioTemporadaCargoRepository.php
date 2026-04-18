<?php

namespace App\Repository;

use App\Entity\Cargo;
use App\Entity\Entidad;
use App\Entity\TemporadaEntidad;
use App\Entity\Usuario;
use App\Entity\UsuarioTemporadaCargo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UsuarioTemporadaCargo>
 */
class UsuarioTemporadaCargoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UsuarioTemporadaCargo::class);
    }

    /**
     * @return list<UsuarioTemporadaCargo>
     */
    public function findByUsuario(Usuario|string $usuario): array
    {
        return $this->createQueryBuilder('utc')
            ->leftJoin('utc.temporada', 't')->addSelect('t')
            ->leftJoin('utc.cargo', 'c')->addSelect('c')
            ->andWhere('utc.usuario = :usuario')
            ->setParameter('usuario', $usuario)
            ->orderBy('t.codigo', 'DESC')
            ->addOrderBy('utc.principal', 'DESC')
            ->addOrderBy('utc.orden', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<UsuarioTemporadaCargo>
     */
    public function findByUsuarioAndEntidad(Usuario|string $usuario, Entidad|string $entidad): array
    {
        return $this->createQueryBuilder('utc')
            ->leftJoin('utc.temporada', 't')->addSelect('t')
            ->leftJoin('utc.cargo', 'c')->addSelect('c')
            ->andWhere('utc.usuario = :usuario')
            ->andWhere('t.entidad = :entidad')
            ->setParameter('usuario', $usuario)
            ->setParameter('entidad', $entidad)
            ->orderBy('t.codigo', 'DESC')
            ->addOrderBy('utc.principal', 'DESC')
            ->addOrderBy('utc.orden', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<UsuarioTemporadaCargo>
     */
    public function findByUsuarioAndTemporada(
        Usuario|string $usuario,
        TemporadaEntidad|string $temporada
    ): array {
        return $this->createQueryBuilder('utc')
            ->leftJoin('utc.cargo', 'c')->addSelect('c')
            ->andWhere('utc.usuario = :usuario')
            ->andWhere('utc.temporada = :temporada')
            ->setParameter('usuario', $usuario)
            ->setParameter('temporada', $temporada)
            ->orderBy('utc.principal', 'DESC')
            ->addOrderBy('utc.orden', 'ASC')
            ->addOrderBy('c.ordenJerarquico', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<UsuarioTemporadaCargo>
     */
    public function findByTemporada(TemporadaEntidad|string $temporada): array
    {
        return $this->createQueryBuilder('utc')
            ->leftJoin('utc.usuario', 'u')->addSelect('u')
            ->leftJoin('utc.cargo', 'c')->addSelect('c')
            ->andWhere('utc.temporada = :temporada')
            ->setParameter('temporada', $temporada)
            ->orderBy('u.nombreCompleto', 'ASC')
            ->addOrderBy('utc.principal', 'DESC')
            ->addOrderBy('utc.orden', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<UsuarioTemporadaCargo>
     */
    public function findUsuariosConCargoEnTemporada(
        TemporadaEntidad|string $temporada,
        Cargo|string $cargo
    ): array {
        return $this->createQueryBuilder('utc')
            ->leftJoin('utc.usuario', 'u')->addSelect('u')
            ->andWhere('utc.temporada = :temporada')
            ->andWhere('utc.cargo = :cargo')
            ->setParameter('temporada', $temporada)
            ->setParameter('cargo', $cargo)
            ->orderBy('u.nombreCompleto', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findCargoPrincipalDeUsuarioEnTemporada(
        Usuario|string $usuario,
        TemporadaEntidad|string $temporada
    ): ?UsuarioTemporadaCargo {
        return $this->createQueryBuilder('utc')
            ->leftJoin('utc.cargo', 'c')->addSelect('c')
            ->andWhere('utc.usuario = :usuario')
            ->andWhere('utc.temporada = :temporada')
            ->setParameter('usuario', $usuario)
            ->setParameter('temporada', $temporada)
            ->orderBy('utc.principal', 'DESC')
            ->addOrderBy('utc.orden', 'ASC')
            ->addOrderBy('c.ordenJerarquico', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countTemporadasComputablesUsuarioEnEntidad(
        Usuario|string $usuario,
        Entidad|string $entidad
    ): int {
        return (int) $this->createQueryBuilder('utc')
            ->select('COUNT(DISTINCT t.id)')
            ->innerJoin('utc.temporada', 't')
            ->andWhere('utc.usuario = :usuario')
            ->andWhere('t.entidad = :entidad')
            ->andWhere('utc.computaAntiguedad = true')
            ->setParameter('usuario', $usuario)
            ->setParameter('entidad', $entidad)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countTemporadasDirectivoUsuarioEnEntidad(
        Usuario|string $usuario,
        Entidad|string $entidad
    ): int {
        return (int) $this->createQueryBuilder('utc')
            ->select('COUNT(DISTINCT t.id)')
            ->innerJoin('utc.temporada', 't')
            ->innerJoin('utc.cargo', 'c')
            ->andWhere('utc.usuario = :usuario')
            ->andWhere('t.entidad = :entidad')
            ->andWhere('utc.computaReconocimiento = true')
            ->andWhere('c.computaComoDirectivo = true')
            ->setParameter('usuario', $usuario)
            ->setParameter('entidad', $entidad)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumAniosExtraUsuarioEnEntidad(
        Usuario|string $usuario,
        Entidad|string $entidad
    ): float {
        $result = $this->createQueryBuilder('utc')
            ->select('COALESCE(SUM(utc.aniosExtraAplicados), 0)')
            ->innerJoin('utc.temporada', 't')
            ->andWhere('utc.usuario = :usuario')
            ->andWhere('t.entidad = :entidad')
            ->setParameter('usuario', $usuario)
            ->setParameter('entidad', $entidad)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $result;
    }

    public function getAntiguedadPonderadaUsuarioEnEntidad(
        Usuario|string $usuario,
        Entidad|string $entidad
    ): float {
        $temporadas = $this->countTemporadasComputablesUsuarioEnEntidad($usuario, $entidad);
        $extras = $this->sumAniosExtraUsuarioEnEntidad($usuario, $entidad);

        return $temporadas + $extras;
    }

    public function countTemporadasPorCodigoCargoUsuarioEnEntidad(
        Usuario|string $usuario,
        Entidad|string $entidad,
        string $codigoCargo
    ): int {
        return (int) $this->createQueryBuilder('utc')
            ->select('COUNT(DISTINCT t.id)')
            ->innerJoin('utc.temporada', 't')
            ->innerJoin('utc.cargo', 'c')
            ->andWhere('utc.usuario = :usuario')
            ->andWhere('t.entidad = :entidad')
            ->andWhere('c.codigo = :codigoCargo')
            ->setParameter('usuario', $usuario)
            ->setParameter('entidad', $entidad)
            ->setParameter('codigoCargo', $codigoCargo)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countTemporadasInfantilesEspecialesUsuarioEnEntidad(
        Usuario|string $usuario,
        Entidad|string $entidad
    ): int {
        return (int) $this->createQueryBuilder('utc')
            ->select('COUNT(DISTINCT t.id)')
            ->innerJoin('utc.temporada', 't')
            ->innerJoin('utc.cargo', 'c')
            ->andWhere('utc.usuario = :usuario')
            ->andWhere('t.entidad = :entidad')
            ->andWhere('utc.computaReconocimiento = true')
            ->andWhere('c.esInfantilEspecial = true')
            ->setParameter('usuario', $usuario)
            ->setParameter('entidad', $entidad)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countTemporadasInfantilesUsuarioEnEntidad(
        Usuario|string $usuario,
        Entidad|string $entidad
    ): int {
        return (int) $this->createQueryBuilder('utc')
            ->select('COUNT(DISTINCT t.id)')
            ->innerJoin('utc.temporada', 't')
            ->andWhere('utc.usuario = :usuario')
            ->andWhere('t.entidad = :entidad')
            ->andWhere('utc.computaReconocimiento = true')
            ->andWhere('utc.esInfantil = true')
            ->setParameter('usuario', $usuario)
            ->setParameter('entidad', $entidad)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
