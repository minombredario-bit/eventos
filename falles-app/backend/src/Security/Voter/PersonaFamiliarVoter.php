<?php

namespace App\Security\Voter;

use App\Entity\PersonaFamiliar;
use App\Entity\Usuario;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter para controlar acceso a Personas Familiares.
 * 
 * Atributos:
 * - VIEW: Ver persona familiar
 * - EDIT: Editar persona familiar
 * - DELETE: Eliminar persona familiar
 */
class PersonaFamiliarVoter extends Voter
{
    public const VIEW = 'PERSONA_FAMILIAR_VIEW';
    public const EDIT = 'PERSONA_FAMILIAR_EDIT';
    public const DELETE = 'PERSONA_FAMILIAR_DELETE';

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
            && $subject instanceof PersonaFamiliar;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        
        if (!$user instanceof Usuario) {
            return false;
        }

        /** @var PersonaFamiliar $familiar */
        $familiar = $subject;

        return match ($attribute) {
            self::VIEW, self::LEGACY_VIEW => $this->canView($familiar, $user),
            self::EDIT, self::LEGACY_EDIT => $this->canEdit($familiar, $user),
            self::DELETE, self::LEGACY_DELETE => $this->canDelete($familiar, $user),
            default => false,
        };
    }

    /**
     * Puede ver la persona familiar si:
     * - Es el usuario principal al que pertenece
     * - Es admin de la entidad
     * - Es superadmin
     */
    private function canView(PersonaFamiliar $familiar, Usuario $user): bool
    {
        if ($this->isOwner($familiar, $user)) {
            return true;
        }

        if ($this->isAdminOfEntidad($user, $familiar)) {
            return true;
        }

        return $this->isSuperadmin($user);
    }

    /**
     * Puede editar si:
     * - Es el usuario principal
     * - Es admin de la entidad
     * - Es superadmin
     */
    private function canEdit(PersonaFamiliar $familiar, Usuario $user): bool
    {
        if ($this->isOwner($familiar, $user)) {
            return true;
        }

        if ($this->isAdminOfEntidad($user, $familiar)) {
            return true;
        }

        return $this->isSuperadmin($user);
    }

    /**
     * Puede eliminar si:
     * - Es el usuario principal
     * - Es admin de la entidad
     * - Es superadmin
     */
    private function canDelete(PersonaFamiliar $familiar, Usuario $user): bool
    {
        if ($this->isOwner($familiar, $user)) {
            return true;
        }

        if ($this->isAdminOfEntidad($user, $familiar)) {
            return true;
        }

        return $this->isSuperadmin($user);
    }

    private function isOwner(PersonaFamiliar $familiar, Usuario $user): bool
    {
        return $familiar->getUsuarioPrincipal()?->getId() === $user->getId();
    }

    private function isAdminOfEntidad(Usuario $user, PersonaFamiliar $familiar): bool
    {
        if (!in_array('ROLE_ADMIN_ENTIDAD', $user->getRoles(), true)) {
            return false;
        }

        return $user->getEntidad()?->getId() === $familiar->getUsuarioPrincipal()?->getEntidad()?->getId();
    }

    private function isSuperadmin(Usuario $user): bool
    {
        return in_array('ROLE_SUPERADMIN', $user->getRoles(), true);
    }
}
