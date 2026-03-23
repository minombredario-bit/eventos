<?php

namespace App\Security\Voter;

use App\Entity\Entidad;
use App\Entity\Usuario;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter para controlar acceso a Entidades.
 * 
 * Atributos:
 * - VIEW: Ver entidad
 * - EDIT: Editar entidad (solo superadmin)
 * - DELETE: Eliminar entidad (solo superadmin)
 */
class EntidadVoter extends Voter
{
    public const VIEW = 'ENTIDAD_VIEW';
    public const EDIT = 'ENTIDAD_EDIT';
    public const DELETE = 'ENTIDAD_DELETE';

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
            && $subject instanceof Entidad;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        
        if (!$user instanceof Usuario) {
            return false;
        }

        /** @var Entidad $entidad */
        $entidad = $subject;

        return match ($attribute) {
            self::VIEW, self::LEGACY_VIEW => $this->canView($entidad, $user),
            self::EDIT, self::LEGACY_EDIT => $this->canEdit($entidad, $user),
            self::DELETE, self::LEGACY_DELETE => $this->canDelete($entidad, $user),
            default => false,
        };
    }

    /**
     * Puede ver la entidad si:
     * - Es superadmin (ve todas)
     * - Es usuario autenticado de la entidad
     */
    private function canView(Entidad $entidad, Usuario $user): bool
    {
        if ($this->isSuperadmin($user)) {
            return true;
        }

        // Cualquier usuario puede ver su propia entidad
        return $user->getEntidad()?->getId() === $entidad->getId();
    }

    /**
     * Solo superadmin puede editar entidades.
     */
    private function canEdit(Entidad $entidad, Usuario $user): bool
    {
        return $this->isSuperadmin($user);
    }

    /**
     * Solo superadmin puede eliminar entidades.
     */
    private function canDelete(Entidad $entidad, Usuario $user): bool
    {
        return $this->isSuperadmin($user);
    }

    private function isSuperadmin(Usuario $user): bool
    {
        return in_array('ROLE_SUPERADMIN', $user->getRoles(), true);
    }
}
