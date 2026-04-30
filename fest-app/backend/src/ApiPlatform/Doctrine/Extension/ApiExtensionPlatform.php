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
use App\Entity\Inscripcion;
use App\Entity\Pago;
use App\Entity\Cargo;
use App\Entity\TipoEntidadCargo;
use App\Entity\TipoEntidad;
use App\Entity\Usuario;
use App\Enum\EstadoEventoEnum;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('api_platform.doctrine.orm.query_extension.collection')]
#[AutoconfigureTag('api_platform.doctrine.orm.query_extension.item')]
final class ApiExtensionPlatform implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->addWhere($queryBuilder, $queryNameGenerator, $resourceClass, false, $operation, $context);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->addWhere($queryBuilder, $queryNameGenerator, $resourceClass, true, $operation, $context);
    }

    private function addWhere(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        bool $isItemOperation = false,
        ?Operation $operation = null,
        array $context = []
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
                $this->addWhereCargoMaster($queryBuilder, $rootAlias, $entidad,  $isItemOperation);
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
                $this->addWhereEvento($queryBuilder, $rootAlias, $entidad, $operation, $context);
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

            case Inscripcion::class:
                $this->addWhereInscripcion($queryBuilder, $rootAlias, $currentUser);
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

        if (!$this->security->isGranted('ROLE_ADMIN_ENTIDAD')) {
            $queryBuilder
                ->andWhere(sprintf('%s.fechaBajaCenso IS NULL', $rootAlias))
                ->andWhere(sprintf('%s.activo = :usuario_activo', $rootAlias))
                ->setParameter('usuario_activo', true);
        }
    }

    private function addWhereEvento(
        QueryBuilder $queryBuilder,
        string $rootAlias,
        Entidad $entidad,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $parameterName = 'evento_entidad';

        $queryBuilder
            ->andWhere(sprintf('%s.entidad = :%s', $rootAlias, $parameterName))
            ->setParameter($parameterName, $entidad);

        // If the current user is not admin entidad we always restrict.
        // If user is ROLE_ADMIN_ENTIDAD we still restrict when the call comes from the "user panel".
        $isAdminEntidad = $this->security->isGranted('ROLE_ADMIN_ENTIDAD');
        $calledFromAdminPanel = $this->isCalledFromAdminPanel($operation, $context);

        if (!$isAdminEntidad || ($isAdminEntidad && !$calledFromAdminPanel)) {
            // Mostrar solo eventos que NO sean borrador y sean visibles
            $queryBuilder
                ->andWhere(sprintf('%s.estado != :%s_borrador', $rootAlias, $parameterName))
                ->andWhere(sprintf('%s.visible = :%s_visible', $rootAlias, $parameterName))
                ->setParameter(sprintf('%s_borrador', $parameterName), EstadoEventoEnum::BORRADOR)
                ->setParameter(sprintf('%s_visible', $parameterName), true);
        }
    }

    /**
     * Determina si la petición actual proviene del panel de administrador.
     * Prioriza la cabecera HTTP 'X-Client-Panel: admin' pero, si el frontend
     * no la envía, permite como fallback el query param '_panel=admin'.
     */
    private function isCalledFromAdminPanel(?Operation $operation, array $context): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return false;
        }

        // Preferimos la cabecera explícita
        $header = $request->headers->get('X-Client-Panel');
        if ($header !== null) {
            $value = strtolower($header);
            if (stripos($value, 'panel') !== false || stripos($value, 'admin') !== false) {
                return true;
            }
        }

        // Fallback: query param (útil cuando el cliente no puede añadir cabeceras)
        $panel = $request->query->get('_panel');
        if ($panel !== null) {
            $p = strtolower($panel);
            if (stripos($p, 'panel') !== false || stripos($p, 'admin') !== false) {
                return true;
            }
        }

        return false;
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
            // Mismo criterio que addWhereEvento: estado != borrador y visible = true
            $queryBuilder
                ->andWhere(sprintf('%s.estado != :%s_borrador', $eventoAlias, $parameterName))
                ->andWhere(sprintf('%s.visible = :%s_visible', $eventoAlias, $parameterName))
                ->setParameter(sprintf('%s_borrador', $parameterName), EstadoEventoEnum::BORRADOR)
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
        Entidad $entidad,
        bool $isItemOperation = false
    ): void {
        $tipoEntidad = $entidad->getTipoEntidad();

        if (!$tipoEntidad instanceof TipoEntidad || $tipoEntidad->getId() === null) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $tipoEntidadCargoAlias = 'tec_' . $rootAlias;
        $parameterName = 'cargo_master_tipo_entidad';

        $queryBuilder
            ->innerJoin(
                TipoEntidadCargo::class,
                $tipoEntidadCargoAlias,
                'WITH',
                sprintf('%s.cargoMaster = %s', $tipoEntidadCargoAlias, $rootAlias)
            )
            ->andWhere(sprintf('%s.tipoEntidad = :%s', $tipoEntidadCargoAlias, $parameterName))
            ->andWhere(sprintf('%s.activo = true', $tipoEntidadCargoAlias))
            ->setParameter($parameterName, $tipoEntidad);

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

        $request = $this->requestStack->getCurrentRequest();
        $tipoPersona = $request?->query->get('tipoPersona');

        $cargoAlias = 'ec_cargo_filter';
        $cargoMasterAlias = 'ec_cargo_master_filter';

        $queryBuilder
            ->leftJoin(sprintf('%s.cargo', $rootAlias), $cargoAlias)
            ->leftJoin(sprintf('%s.cargoMaster', $rootAlias), $cargoMasterAlias);

        // Siempre exigir que el cargo relacionado esté activo
        $queryBuilder->andWhere(sprintf(
            '(
            (%s.id IS NOT NULL AND %s.activo = true)
            OR
            (%s.id IS NOT NULL AND %s.activo = true)
        )',
            $cargoAlias,
            $cargoAlias,
            $cargoMasterAlias,
            $cargoMasterAlias
        ));

        if (!\in_array($tipoPersona, ['adulto', 'infantil'], true)) {
            return;
        }

        if ($tipoPersona === 'infantil') {
            $queryBuilder->andWhere(sprintf(
                '(
                (%s.id IS NOT NULL AND (%s.esInfantil = true OR %s.infantilEspecial = true))
                OR
                (%s.id IS NOT NULL AND (%s.esInfantil = true OR %s.infantilEspecial = true))
            )',
                $cargoAlias,
                $cargoAlias,
                $cargoAlias,
                $cargoMasterAlias,
                $cargoMasterAlias,
                $cargoMasterAlias
            ));

            return;
        }

        $queryBuilder->andWhere(sprintf(
            '(
            (%s.id IS NOT NULL AND %s.esInfantil = false AND %s.infantilEspecial = false)
            OR
            (%s.id IS NOT NULL AND %s.esInfantil = false AND %s.infantilEspecial = false)
        )',
            $cargoAlias,
            $cargoAlias,
            $cargoAlias,
            $cargoMasterAlias,
            $cargoMasterAlias,
            $cargoMasterAlias
        ));
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
        $queryBuilder->andWhere(sprintf('%s.activo = true', $rootAlias));

    }

    private function addWhereInscripcion(
        QueryBuilder $queryBuilder,
        string $rootAlias,
        Usuario $currentUser
    ): void {
        $parameterName = 'usuario';
        $queryBuilder
            ->andWhere(sprintf('%s.usuario = :%s_usuario', $rootAlias, $parameterName))
            ->setParameter(sprintf('%s_usuario', $parameterName), $currentUser);

    }
}
