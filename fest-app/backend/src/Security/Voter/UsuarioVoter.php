<?php

namespace App\Security\Voter;

use App\Entity\Usuario;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter para controlar acceso a Usuarios.
 *
 * Atributos:
 * - VIEW: Ver perfil de usuario
 * - EDIT: Editar perfil de usuario
 * - DELETE: Eliminar usuario (solo superadmin)
 */
class UsuarioVoter extends Voter
{
    public const VIEW = 'USUARIO_VIEW';
    public const EDIT = 'USUARIO_EDIT';
    public const DELETE = 'USUARIO_DELETE';

    private const LEGACY_VIEW = 'VIEW';
    private const LEGACY_EDIT = 'EDIT';
    private const LEGACY_DELETE = 'DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::DELETE,
            self::LEGACY_VIEW,
            self::LEGACY_EDIT,
            self::LEGACY_DELETE,
        ], true)
            && $subject instanceof Usuario;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof Usuario) {
            return false;
        }

        /** @var Usuario $targetUser */
        $targetUser = $subject;

        return match ($attribute) {
            self::VIEW, self::LEGACY_VIEW => $this->canView($targetUser, $user),
            self::EDIT, self::LEGACY_EDIT => $this->canEdit($targetUser, $user),
            self::DELETE, self::LEGACY_DELETE => $this->canDelete($targetUser, $user),
            default => false,
        };
    }

    /**
     * Puede ver el usuario si:
     * - Es superadmin
     * - Es admin de la misma entidad
     * - Es el propio usuario
     */
    private function canView(Usuario $targetUser, Usuario $user): bool
    {
        if ($this->isSuperadmin($user)) {
            return true;
        }

        if ($this->isAdminOfSameEntidad($user, $targetUser)) {
            return true;
        }

        // El usuario puede ver su propio perfil
        return $user->getId() === $targetUser->getId();
    }

    /**
     * Puede editar el usuario si:
     * - Es superadmin
     * - Es admin de la misma entidad
     */
    private function canEdit(Usuario $targetUser, Usuario $user): bool
    {
        if ($this->isSuperadmin($user)) {
            return true;
        }

        if ($this->isAdminOfSameEntidad($user, $targetUser)) {
            return true;
        }

        return false;
    }

    /**
     * Puede eliminar solo el superadmin.
     */
    private function canDelete(Usuario $targetUser, Usuario $user): bool
    {
        return $this->isSuperadmin($user);
    }

    private function isSuperadmin(Usuario $user): bool
    {
        return in_array('ROLE_SUPERADMIN', $user->getRoles(), true);
    }

    private function isAdminOfSameEntidad(Usuario $user, Usuario $targetUser): bool
    {
        if (!in_array('ROLE_ADMIN_ENTIDAD', $user->getRoles(), true)) {
            return false;
        }

        return $user->getEntidad()?->getId() === $targetUser->getEntidad()?->getId();
    }
}
