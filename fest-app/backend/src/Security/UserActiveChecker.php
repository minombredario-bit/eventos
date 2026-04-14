<?php

namespace App\Security;

use App\Entity\Usuario;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserActiveChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Usuario) {
            return;
        }

        if (!$user->isActivo()) {
            throw new CustomUserMessageAccountStatusException('Tu usuario está dado de baja o bloqueado.');
        }

        if (!$user->getEntidad()->isActiva()) {
            throw new CustomUserMessageAccountStatusException('La entidad está inactiva. Contacta con administración.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // No post-auth checks required.
    }
}

