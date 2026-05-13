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

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::DELETE
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
            self::VIEW => $this->canView($evento, $user),
            self::EDIT => $this->canEdit($evento, $user),
            self::DELETE => $this->canDelete($evento, $user),
            default => false,
        };
    }

    /**
     * Puede ver el evento si:
     * - Está publicado y el usuario tiene ROLE_USER
     * - Es admin de la entidad (ve todos, publicados y no)
     * - Es gestor de eventos de la entidad (ROLE_EVENTO)
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

        // Gestores de eventos ven todos los eventos (publicados y no) de su entidad
        if ($this->isEventoManagerOfEntidad($user, $evento->getEntidad()->getId())) {
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
     * - Es gestor de eventos de la entidad (ROLE_EVENTO)
     * - Es superadmin
     */
    private function canEdit(Evento $evento, Usuario $user): bool
    {
        if ($this->isAdminOfEntidad($user, $evento->getEntidad()->getId())) {
            return true;
        }

        if ($this->isEventoManagerOfEntidad($user, $evento->getEntidad()->getId())) {
            return true;
        }

        return $this->isSuperadmin($user);
    }

    /**
     * Puede eliminar si:
     * - Es admin de la entidad (siempre)
     * - Es superadmin (siempre)
     * - Es gestor de eventos (ROLE_EVENTO) de la entidad Y el evento no tiene líneas pagadas.
     */
    private function canDelete(Evento $evento, Usuario $user): bool
    {
        if ($this->isAdminOfEntidad($user, $evento->getEntidad()->getId())) {
            return true;
        }

        if ($this->isSuperadmin($user)) {
            return true;
        }

        if ($this->isEventoManagerOfEntidad($user, $evento->getEntidad()->getId())) {
            return !$this->eventoTienePagos($evento);
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

    /**
     * Comprueba que el usuario tiene ROLE_EVENTO y pertenece a la entidad indicada.
     */
    private function isEventoManagerOfEntidad(Usuario $user, string $entidadId): bool
    {
        if (!in_array('ROLE_EVENTO', $user->getRoles(), true)) {
            return false;
        }

        return $user->getEntidad()?->getId() === $entidadId;
    }

    private function isSuperadmin(Usuario $user): bool
    {
        return in_array('ROLE_SUPERADMIN', $user->getRoles(), true);
    }

    /**
     * Devuelve true si el evento tiene al menos una línea de inscripción pagada.
     * Se recorre la colección de inscripciones ya cargada en la entidad.
     */
    private function eventoTienePagos(Evento $evento): bool
    {
        foreach ($evento->getInscripciones() as $inscripcion) {
            foreach ($inscripcion->getLineas() as $linea) {
                if ($linea->isPagada()) {
                    return true;
                }
            }
        }

        return false;
    }
}
