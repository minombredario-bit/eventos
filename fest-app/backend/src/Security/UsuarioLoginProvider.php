<?php

namespace App\Security;

use App\Entity\Usuario;
use App\Repository\UsuarioRepository;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class UsuarioLoginProvider implements UserProviderInterface
{
    public function __construct(
        private readonly UsuarioRepository $usuarioRepository,
    ) {}

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $identifier = trim($identifier);

        $usuario = $this->usuarioRepository->createQueryBuilder('u')
            ->andWhere('LOWER(u.email) = LOWER(:identifier) OR u.documentoIdentidad = :identifier')
            ->setParameter('identifier', $identifier)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$usuario) {
            throw new UserNotFoundException();
        }

        return $usuario;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof Usuario || !$user->getId()) {
            throw new UserNotFoundException();
        }

        return $this->usuarioRepository->find($user->getId())
            ?? throw new UserNotFoundException();
    }

    public function supportsClass(string $class): bool
    {
        return Usuario::class === $class || is_subclass_of($class, Usuario::class);
    }
}
