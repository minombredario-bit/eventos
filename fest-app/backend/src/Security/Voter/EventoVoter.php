<?php

namespace App\Security\Voter;

use App\Entity\Evento;
use App\Entity\Usuario;
use App\Enum\EstadoEventoEnum;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter para controlar acceso a Eventos.
 *
 * Atributos:
 * - VIEW: Ver evento (publicados para todos, no publicados para admins)
 * - EDIT: Editar evento
 * - DELETE: Eliminar evento
 */
class EventoVoter extends Voter
{
    public const VIEW = 'EVENTO_VIEW';
    public const EDIT = 'EVENTO_EDIT';
    public const DELETE = 'EVENTO_DELETE';

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
            && $subject instanceof Evento;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof Usuario) {
            return false;
        }

        /** @var Evento $evento */
        $evento = $subject;

        return match ($attribute) {
            self::VIEW, self::LEGACY_VIEW => $this->canView($evento, $user),
            self::EDIT, self::LEGACY_EDIT => $this->canEdit($evento, $user),
            self::DELETE, self::LEGACY_DELETE => $this->canDelete($evento, $user),
            default => false,
        };
    }

    /**
     * Puede ver el evento si:
     * - Está publicado y el usuario tiene ROLE_USER
     * - Es admin de la entidad (ve todos, publicados y no)
     * - Es superadmin
     */
    private function canView(Evento $evento, Usuario $user): bool
    {
        // Eventos publicados visibles para cualquier usuario autenticado
        if ($evento->getEstado() === EstadoEventoEnum::PUBLICADO) {
            return true;
        }

        // Admins y superadmins ven todos los eventos de su entidad
        if ($this->isAdminOfEntidad($user, $evento->getEntidad()->getId())) {
            return true;
        }

        if ($this->isSuperadmin($user)) {
            return true;
        }

        return false;
    }

    /**
     * Puede editar si:
     * - Es admin de la entidad
     * - Es superadmin
     */
    private function canEdit(Evento $evento, Usuario $user): bool
    {
        if ($this->isAdminOfEntidad($user, $evento->getEntidad()->getId())) {
            return true;
        }

        return $this->isSuperadmin($user);
    }

    /**
     * Puede eliminar si:
     * - Es admin de la entidad
     * - Es superadmin
     */
    private function canDelete(Evento $evento, Usuario $user): bool
    {
        if ($this->isAdminOfEntidad($user, $evento->getEntidad()->getId())) {
            return true;
        }

        return $this->isSuperadmin($user);
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
