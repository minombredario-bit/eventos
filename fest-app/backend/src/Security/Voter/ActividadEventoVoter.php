<?php

namespace App\Security\Voter;

use App\Entity\ActividadEvento;
use App\Entity\Usuario;
use App\Repository\InscripcionLineaRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter para controlar el borrado de ActividadEvento.
 *
 * Atributos:
 * - ACTIVIDAD_DELETE: Eliminar actividad
 *   - ROLE_ADMIN_ENTIDAD y ROLE_SUPERADMIN: siempre permitido.
 *   - ROLE_EVENTO: sólo si no existen líneas de inscripción pagadas para esa actividad.
 */
class ActividadEventoVoter extends Voter
{
    public const DELETE = 'ACTIVIDAD_DELETE';

    public function __construct(
        private readonly InscripcionLineaRepository $inscripcionLineaRepository,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::DELETE
            && $subject instanceof ActividadEvento;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof Usuario) {
            return false;
        }

        /** @var ActividadEvento $actividad */
        $actividad = $subject;

        return $this->canDelete($actividad, $user);
    }

    /**
     * Puede eliminar si:
     * - Es admin de la entidad (siempre).
     * - Es superadmin (siempre).
     * - Es gestor de eventos (ROLE_EVENTO) de la entidad Y la actividad no tiene líneas pagadas.
     */
    private function canDelete(ActividadEvento $actividad, Usuario $user): bool
    {
        $entidadId = $actividad->getEvento()->getEntidad()->getId();

        if ($this->isAdminOfEntidad($user, $entidadId)) {
            return true;
        }

        if ($this->isSuperadmin($user)) {
            return true;
        }

        if ($this->isEventoManagerOfEntidad($user, $entidadId)) {
            return !$this->inscripcionLineaRepository->existePagadaForActividad($actividad);
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
}

