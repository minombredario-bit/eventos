<?php

namespace App\Security\Voter;

use App\Entity\Inscripcion;
use App\Entity\Usuario;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter para controlar acceso a Inscripciones.
 * 
 * Atributos soportados:
 * - VIEW: Ver una inscripción
 * - EDIT: Editar una inscripción (solo admins)
 * - DELETE: Eliminar una inscripción (solo admins)
 * 
 * Uso en entidades API Platform:
 * #[ApiResource(security: "is_granted('VIEW', object)")]
 */
class InscripcionVoter extends Voter
{
    public const VIEW = 'INSCRIPCION_VIEW';
    public const EDIT = 'INSCRIPCION_EDIT';
    public const DELETE = 'INSCRIPCION_DELETE';

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
            && $subject instanceof Inscripcion;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        
        if (!$user instanceof Usuario) {
            return false;
        }

        /** @var Inscripcion $inscripcion */
        $inscripcion = $subject;

        return match ($attribute) {
            self::VIEW, self::LEGACY_VIEW => $this->canView($inscripcion, $user),
            self::EDIT, self::LEGACY_EDIT => $this->canEdit($inscripcion, $user),
            self::DELETE, self::LEGACY_DELETE => $this->canDelete($inscripcion, $user),
            default => false,
        };
    }

    /**
     * Puede ver la inscripción si:
     * - Es el usuario dueño de la inscripción
     * - Es admin de la entidad
     * - Es superadmin
     */
    private function canView(Inscripcion $inscripcion, Usuario $user): bool
    {
        // El usuario dueño puede ver su propia inscripción
        if ($inscripcion->getUsuario()->getId() === $user->getId()) {
            return true;
        }

        // Los admins de la entidad pueden ver todas las inscripciones de su entidad
        if ($this->isAdminOfEntidad($user, $inscripcion->getEntidad()->getId())) {
            return true;
        }

        // Los superadmins pueden ver todo
        if ($this->isSuperadmin($user)) {
            return true;
        }

        return false;
    }

    /**
     * Puede editar la inscripción si:
     * - Es admin de la entidad
     * - Es superadmin
     */
    private function canEdit(Inscripcion $inscripcion, Usuario $user): bool
    {
        // Los admins de la entidad pueden editar
        if ($this->isAdminOfEntidad($user, $inscripcion->getEntidad()->getId())) {
            return true;
        }

        // Los superadmins pueden editar todo
        if ($this->isSuperadmin($user)) {
            return true;
        }

        return false;
    }

    /**
     * Puede eliminar la inscripción si:
     * - Es admin de la entidad
     * - Es superadmin
     */
    private function canDelete(Inscripcion $inscripcion, Usuario $user): bool
    {
        // Solo admins y superadmins pueden eliminar
        if ($this->isAdminOfEntidad($user, $inscripcion->getEntidad()->getId())) {
            return true;
        }

        if ($this->isSuperadmin($user)) {
            return true;
        }

        return false;
    }

    private function isAdminOfEntidad(Usuario $user, string $entidadId): bool
    {
        if (!in_array('ROLE_ADMIN_ENTIDAD', $user->getRoles(), true)) {
            return false;
        }

        return $user->getEntidad()?->getId() === $entidadId;
    }

    private function isSuperadmin(Usuario $user): bool
    {
        return in_array('ROLE_SUPERADMIN', $user->getRoles(), true);
    }
}
