<?php

namespace App\ApiPlatform\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Inscripcion;
use App\Entity\Usuario;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

final class InscripcionOwnerExtension implements QueryCollectionExtensionInterface
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
        if (!is_a($resourceClass, Inscripcion::class, true)) {
            return;
        }

        if ($this->security->isGranted('ROLE_ADMIN_ENTIDAD') || $this->security->isGranted('ROLE_SUPERADMIN')) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0] ?? null;
        if ($rootAlias === null) {
            return;
        }

        $user = $this->security->getUser();
        if ($user instanceof Usuario) {
            $queryBuilder
                ->andWhere(sprintf('%s.usuario = :current_user', $rootAlias))
                ->setParameter('current_user', $user);

            return;
        }

        if ($user instanceof UserInterface) {
            $identifier = trim($user->getUserIdentifier());

            if ($identifier === '') {
                $queryBuilder->andWhere('1 = 0');
                return;
            }

            $usuarioAlias = $queryNameGenerator->generateJoinAlias('usuario');

            $queryBuilder
                ->innerJoin(sprintf('%s.usuario', $rootAlias), $usuarioAlias)
                ->andWhere(sprintf('%s.email = :current_user_identifier', $usuarioAlias))
                ->setParameter('current_user_identifier', $identifier);

            return;
        }

        $queryBuilder->andWhere('1 = 0');
    }
}
