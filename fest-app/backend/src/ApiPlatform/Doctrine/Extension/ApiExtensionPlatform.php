<?php

namespace App\ApiPlatform\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\ActividadEvento;
use App\Entity\Entidad;
use App\Entity\Evento;
use App\Entity\Pago;
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

            case Usuario::class:
                $this->addWhereUsuario($queryBuilder, $rootAlias, $entidad, $isItemOperation);
                break;

            case Evento::class:
                $this->addWhereEvento($queryBuilder, $rootAlias, $entidad);
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
}
