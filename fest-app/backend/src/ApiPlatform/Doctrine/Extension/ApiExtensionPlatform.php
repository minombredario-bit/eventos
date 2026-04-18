<?php

namespace App\ApiPlatform\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\ActividadEvento;
use App\Entity\CargoMaster;
use App\Entity\EntidadCargo;
use App\Entity\Entidad;
use App\Entity\Evento;
use App\Entity\Pago;
use App\Entity\Cargo;
use App\Entity\TipoEntidadCargo;
use App\Entity\TipoEntidad;
use App\Entity\Usuario;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('api_platform.doctrine.orm.query_extension.collection')]
#[AutoconfigureTag('api_platform.doctrine.orm.query_extension.item')]
final class ApiExtensionPlatform implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->addWhere($queryBuilder, $queryNameGenerator, $resourceClass, false);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->addWhere($queryBuilder, $queryNameGenerator, $resourceClass, true);
    }

    private function addWhere(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        bool $isItemOperation = false
    ): void {
        if ($this->security->isGranted('ROLE_SUPERADMIN')) {
            return;
        }

        $currentUser = $this->security->getUser();
        if (!$currentUser instanceof Usuario) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $entidad = $currentUser->getEntidad();
        if ($entidad === null || $entidad->getId() === null) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0] ?? null;
        if ($rootAlias === null) {
            return;
        }

        switch ($resourceClass) {
            case Entidad::class:
                $this->addWhereEntidad($queryBuilder, $rootAlias, $entidad);
                break;

            case CargoMaster::class:
                $this->addWhereCargoMaster($queryBuilder, $rootAlias, $isItemOperation);
                break;

            case EntidadCargo::class:
                $this->addWhereEntidadCargo($queryBuilder, $rootAlias, $entidad, $isItemOperation);
                break;

            case TipoEntidadCargo::class:
                $this->addWhereTipoEntidadCargo($queryBuilder, $rootAlias, $entidad, $isItemOperation);
                break;

            case Usuario::class:
                $this->addWhereUsuario($queryBuilder, $rootAlias, $entidad, $isItemOperation);
                break;

            case Evento::class:
                $this->addWhereEvento($queryBuilder, $rootAlias, $entidad);
                break;

            case Cargo::class:
                $this->addWhereCargo($queryBuilder, $rootAlias, $entidad, $isItemOperation);
                break;

            case ActividadEvento::class:
                $this->addWhereActividadEvento($queryBuilder, $queryNameGenerator, $rootAlias, $entidad);
                break;

            case Pago::class:
                $this->addWherePago($queryBuilder, $queryNameGenerator, $rootAlias, $entidad, $currentUser);
                break;
        }
    }

    private function addWhereEntidad(
        QueryBuilder $queryBuilder,
        string $rootAlias,
        Entidad $entidad
    ): void {
        $parameterName = 'entidad_id';

        $queryBuilder
            ->andWhere(sprintf('%s.id = :%s', $rootAlias, $parameterName))
            ->setParameter($parameterName, $entidad->getId());
    }

    private function addWhereUsuario(
        QueryBuilder $queryBuilder,
        string $rootAlias,
        Entidad $entidad,
        bool $isItemOperation = false
    ): void {
        $parameterName = 'usuario_entidad';

        $queryBuilder
            ->andWhere(sprintf('%s.entidad = :%s', $rootAlias, $parameterName))
            ->setParameter($parameterName, $entidad);

        // Solo en colección y para admin entidad: censados por defecto.
        if (!$isItemOperation && $this->security->isGranted('ROLE_ADMIN_ENTIDAD')) {
            $queryBuilder
                ->andWhere(sprintf('%s.fechaAltaCenso IS NOT NULL', $rootAlias))
                ->andWhere(sprintf('%s.fechaBajaCenso IS NULL', $rootAlias));
        }
    }

    private function addWhereEvento(
        QueryBuilder $queryBuilder,
        string $rootAlias,
        Entidad $entidad
    ): void {
        $parameterName = 'evento_entidad';

        $queryBuilder
            ->andWhere(sprintf('%s.entidad = :%s', $rootAlias, $parameterName))
            ->setParameter($parameterName, $entidad);

        if (!$this->security->isGranted('ROLE_ADMIN_ENTIDAD')) {
            $queryBuilder
                ->andWhere(sprintf('%s.publicado = :%s_publicado', $rootAlias, $parameterName))
                ->andWhere(sprintf('%s.visible = :%s_visible', $rootAlias, $parameterName))
                ->setParameter(sprintf('%s_publicado', $parameterName), true)
                ->setParameter(sprintf('%s_visible', $parameterName), true);
        }
    }

    private function addWhereActividadEvento(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $rootAlias,
        Entidad $entidad
    ): void {
        $eventoAlias = $queryNameGenerator->generateJoinAlias('evento');
        $parameterName = 'actividad_evento_entidad';

        $queryBuilder
            ->innerJoin(sprintf('%s.evento', $rootAlias), $eventoAlias)
            ->andWhere(sprintf('%s.entidad = :%s', $eventoAlias, $parameterName))
            ->setParameter($parameterName, $entidad);

        if (!$this->security->isGranted('ROLE_ADMIN_ENTIDAD')) {
            $queryBuilder
                ->andWhere(sprintf('%s.publicado = :%s_publicado', $eventoAlias, $parameterName))
                ->andWhere(sprintf('%s.visible = :%s_visible', $eventoAlias, $parameterName))
                ->setParameter(sprintf('%s_publicado', $parameterName), true)
                ->setParameter(sprintf('%s_visible', $parameterName), true);
        }
    }

    private function addWherePago(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $rootAlias,
        Entidad $entidad,
        Usuario $currentUser
    ): void {
        $inscripcionAlias = $queryNameGenerator->generateJoinAlias('inscripcion');
        $parameterName = 'pago_entidad';

        $queryBuilder
            ->innerJoin(sprintf('%s.inscripcion', $rootAlias), $inscripcionAlias)
            ->andWhere(sprintf('%s.entidad = :%s', $inscripcionAlias, $parameterName))
            ->setParameter($parameterName, $entidad);

        if (!$this->security->isGranted('ROLE_ADMIN_ENTIDAD')) {
            $queryBuilder
                ->andWhere(sprintf('%s.usuario = :%s_usuario', $inscripcionAlias, $parameterName))
                ->setParameter(sprintf('%s_usuario', $parameterName), $currentUser);
        }
    }

    private function addWhereCargo(
        QueryBuilder $queryBuilder,
        string $rootAlias,
        Entidad $entidad,
        bool $isItemOperation = false
    ): void {
        // Only superadmin may see cargos from all entidades. ROLE_ADMIN and ROLE_ADMIN_ENTIDAD
        // must be restricted to the admin's entidad. For other authenticated users we also
        // restrict to the user's entidad by default.
        $parameterName = 'cargo_entidad';

        $queryBuilder
            ->andWhere(sprintf('%s.entidad = :%s', $rootAlias, $parameterName))
            ->setParameter($parameterName, $entidad);

        if (!$isItemOperation) {
            $queryBuilder->andWhere(sprintf('%s.activo = true', $rootAlias));
        }
    }

    private function addWhereCargoMaster(
        QueryBuilder $queryBuilder,
        string $rootAlias,
        bool $isItemOperation = false
    ): void {
        if (!$isItemOperation) {
            $queryBuilder->andWhere(sprintf('%s.activo = true', $rootAlias));
        }
    }

    private function addWhereEntidadCargo(
        QueryBuilder $queryBuilder,
        string $rootAlias,
        Entidad $entidad,
        bool $isItemOperation = false
    ): void {
        $parameterName = 'entidad_cargo_entidad';

        $queryBuilder
            ->andWhere(sprintf('%s.entidad = :%s', $rootAlias, $parameterName))
            ->setParameter($parameterName, $entidad);

        if (!$isItemOperation) {
            $queryBuilder->andWhere(sprintf('%s.activo = true', $rootAlias));
        }
    }

    private function addWhereTipoEntidadCargo(
        QueryBuilder $queryBuilder,
        string $rootAlias,
        Entidad $entidad,
        bool $isItemOperation = false
    ): void {
        $tipoEntidad = $entidad->getTipoEntidad();
        if (!$tipoEntidad instanceof TipoEntidad || $tipoEntidad->getId() === null) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $parameterName = 'tipo_entidad_cargo_tipo_entidad';

        $queryBuilder
            ->andWhere(sprintf('%s.tipoEntidad = :%s', $rootAlias, $parameterName))
            ->setParameter($parameterName, $tipoEntidad);

        if (!$isItemOperation) {
            $queryBuilder->andWhere(sprintf('%s.activo = true', $rootAlias));
        }
    }
}
