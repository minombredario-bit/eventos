<?php

namespace App\ApiPlatform\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Entidad;
use App\Entity\Evento;
use App\Entity\MenuEvento;
use App\Entity\Pago;
use App\Entity\Usuario;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('api_platform.doctrine.orm.query_extension.collection')]
final class ResourceScopeExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        if (!in_array($resourceClass, [Entidad::class, Evento::class, Usuario::class, MenuEvento::class, Pago::class], true)) {
            return;
        }

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

        $entidadIdParameter = sprintf('%s_entidad_id', str_replace('\\', '_', strtolower($resourceClass)));

        if ($resourceClass === Entidad::class) {
            $queryBuilder
                ->andWhere(sprintf('%s.id = :%s', $rootAlias, $entidadIdParameter))
                ->setParameter($entidadIdParameter, $entidad->getId());

            return;
        }

        if ($resourceClass === Usuario::class) {
            $queryBuilder
                ->andWhere(sprintf('%s.entidad = :%s', $rootAlias, $entidadIdParameter))
                ->setParameter($entidadIdParameter, $entidad);

            return;
        }

        if ($resourceClass === Evento::class) {
            $queryBuilder
                ->andWhere(sprintf('%s.entidad = :%s', $rootAlias, $entidadIdParameter))
                ->setParameter($entidadIdParameter, $entidad);

            if (!$this->security->isGranted('ROLE_ADMIN_ENTIDAD')) {
                $queryBuilder
                    ->andWhere(sprintf('%s.publicado = :%s_publicado', $rootAlias, $entidadIdParameter))
                    ->andWhere(sprintf('%s.visible = :%s_visible', $rootAlias, $entidadIdParameter))
                    ->setParameter(sprintf('%s_publicado', $entidadIdParameter), true)
                    ->setParameter(sprintf('%s_visible', $entidadIdParameter), true);
            }

            return;
        }

        if ($resourceClass === MenuEvento::class) {
            $eventoAlias = $queryNameGenerator->generateJoinAlias('evento');

            $queryBuilder
                ->innerJoin(sprintf('%s.evento', $rootAlias), $eventoAlias)
                ->andWhere(sprintf('%s.entidad = :%s', $eventoAlias, $entidadIdParameter))
                ->setParameter($entidadIdParameter, $entidad);

            if (!$this->security->isGranted('ROLE_ADMIN_ENTIDAD')) {
                $queryBuilder
                    ->andWhere(sprintf('%s.publicado = :%s_publicado', $eventoAlias, $entidadIdParameter))
                    ->andWhere(sprintf('%s.visible = :%s_visible', $eventoAlias, $entidadIdParameter))
                    ->setParameter(sprintf('%s_publicado', $entidadIdParameter), true)
                    ->setParameter(sprintf('%s_visible', $entidadIdParameter), true);
            }

            return;
        }

        if ($resourceClass === Pago::class) {
            $inscripcionAlias = $queryNameGenerator->generateJoinAlias('inscripcion');

            $queryBuilder
                ->innerJoin(sprintf('%s.inscripcion', $rootAlias), $inscripcionAlias)
                ->andWhere(sprintf('%s.entidad = :%s', $inscripcionAlias, $entidadIdParameter))
                ->setParameter($entidadIdParameter, $entidad);

            if (!$this->security->isGranted('ROLE_ADMIN_ENTIDAD')) {
                $queryBuilder
                    ->andWhere(sprintf('%s.usuario = :%s_usuario', $inscripcionAlias, $entidadIdParameter))
                    ->setParameter(sprintf('%s_usuario', $entidadIdParameter), $currentUser);
            }
        }
    }
}
