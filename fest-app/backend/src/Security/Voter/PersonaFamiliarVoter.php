<?php

namespace App\Security\Voter;

use App\Entity\RelacionUsuario;
use App\Entity\Usuario;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter para controlar acceso a relaciones entre usuarios.
 *
 * Atributos:
 * - VIEW: Ver persona familiar
 * - EDIT: Editar persona familiar
 * - DELETE: Eliminar persona familiar
 */
class PersonaFamiliarVoter extends Voter
{
    public const VIEW = 'RELACION_VIEW';
    public const EDIT = 'RELACION_EDIT';
    public const DELETE = 'RELACION_DELETE';

    private const LEGACY_PERSONA_VIEW = 'PERSONA_FAMILIAR_VIEW';
    private const LEGACY_PERSONA_EDIT = 'PERSONA_FAMILIAR_EDIT';
    private const LEGACY_PERSONA_DELETE = 'PERSONA_FAMILIAR_DELETE';

    private const LEGACY_VIEW = 'VIEW';
    private const LEGACY_EDIT = 'EDIT';
    private const LEGACY_DELETE = 'DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::DELETE,
            self::LEGACY_PERSONA_VIEW,
            self::LEGACY_PERSONA_EDIT,
            self::LEGACY_PERSONA_DELETE,
            self::LEGACY_VIEW,
            self::LEGACY_EDIT,
            self::LEGACY_DELETE,
        ], true)
            && $subject instanceof RelacionUsuario;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof Usuario) {
            return false;
        }

        /** @var RelacionUsuario $relacion */
        $relacion = $subject;

        return match ($attribute) {
            self::VIEW, self::LEGACY_PERSONA_VIEW, self::LEGACY_VIEW => $this->canView($relacion, $user),
            self::EDIT, self::LEGACY_PERSONA_EDIT, self::LEGACY_EDIT => $this->canEdit($relacion, $user),
            self::DELETE, self::LEGACY_PERSONA_DELETE, self::LEGACY_DELETE => $this->canDelete($relacion, $user),
            default => false,
        };
    }

    /**
     * Puede ver la persona familiar si:
     * - Es el usuario principal al que pertenece
     * - Es admin de la entidad
     * - Es superadmin
     */
    private function canView(RelacionUsuario $relacion, Usuario $user): bool
    {
        if ($this->isOwner($relacion, $user)) {
            return true;
        }

        if ($this->isAdminOfEntidad($user, $relacion)) {
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
    private function canEdit(RelacionUsuario $relacion, Usuario $user): bool
    {
        if ($this->isOwner($relacion, $user)) {
            return true;
        }

        if ($this->isAdminOfEntidad($user, $relacion)) {
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
    private function canDelete(RelacionUsuario $relacion, Usuario $user): bool
    {
        if ($this->isOwner($relacion, $user)) {
            return true;
        }

        if ($this->isAdminOfEntidad($user, $relacion)) {
            return true;
        }

        return $this->isSuperadmin($user);
    }

    private function isOwner(RelacionUsuario $relacion, Usuario $user): bool
    {
        $userId = $user->getId();

        return $relacion->getUsuarioOrigen()->getId() === $userId
            || $relacion->getUsuarioDestino()->getId() === $userId;
    }

    private function isAdminOfEntidad(Usuario $user, RelacionUsuario $relacion): bool
    {
        if (!in_array('ROLE_ADMIN_ENTIDAD', $user->getRoles(), true)) {
            return false;
        }

        $adminEntidadId = $user->getEntidad()?->getId();
        if ($adminEntidadId === null) {
            return false;
        }

        return $adminEntidadId === $relacion->getUsuarioOrigen()->getEntidad()?->getId()
            || $adminEntidadId === $relacion->getUsuarioDestino()->getEntidad()?->getId();
    }

    private function isSuperadmin(Usuario $user): bool
    {
        return in_array('ROLE_SUPERADMIN', $user->getRoles(), true);
    }
}
