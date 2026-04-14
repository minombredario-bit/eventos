<?php

namespace App\Security\Voter;

use App\Entity\Inscripcion;
use App\Entity\Usuario;
use App\Repository\RelacionUsuarioRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

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
    public function __construct(
        private readonly RelacionUsuarioRepository $relacionUsuarioRepository,
    ) {
    }

    public const VIEW = 'INSCRIPCION_VIEW';
    public const EDIT = 'INSCRIPCION_EDIT';
    public const DELETE = 'INSCRIPCION_DELETE';

    private const LEGACY_VIEW = 'VIEW';
    private const LEGACY_EDIT = 'EDIT';
    private const LEGACY_DELETE = 'DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Inscripcion
            && null !== $this->normalizeAttribute($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $normalizedAttribute = $this->normalizeAttribute($attribute);
        if (null === $normalizedAttribute) {
            return false;
        }

        $user = $token->getUser();

        /** @var Inscripcion $inscripcion */
        $inscripcion = $subject;

        $roles = $token->getRoleNames();
        $isAdminEntidad = in_array('ROLE_ADMIN_ENTIDAD', $roles, true);
        $isSuperadmin = in_array('ROLE_SUPERADMIN', $roles, true);

        return match ($normalizedAttribute) {
            self::VIEW, self::LEGACY_VIEW => $this->canView($inscripcion, $user, $isAdminEntidad || $isSuperadmin),
            self::EDIT, self::LEGACY_EDIT => $this->canEdit($isAdminEntidad || $isSuperadmin),
            self::DELETE, self::LEGACY_DELETE => $this->canDelete($isAdminEntidad || $isSuperadmin),
            default => false,
        };
    }

    /**
     * Puede ver la inscripción si:
     * - Es el usuario dueño de la inscripción
     * - Es admin de la entidad
     * - Es superadmin
     */
    private function canView(Inscripcion $inscripcion, mixed $user, bool $hasPrivilegedRole): bool
    {
        if ($hasPrivilegedRole) {
            return true;
        }

        if (!$user instanceof Usuario && !$user instanceof UserInterface) {
            return false;
        }

        // El usuario dueño puede ver su propia inscripción
        if ($this->isOwner($inscripcion, $user)) {
            return true;
        }

        // También permitimos el acceso a usuarios relacionados con el titular.
        if ($this->isHouseholdRelated($inscripcion, $user)) {
            return true;
        }

        return false;
    }

    private function isHouseholdRelated(Inscripcion $inscripcion, Usuario|UserInterface $user): bool
    {
        if (!$user instanceof Usuario) {
            return false;
        }

        $owner = $inscripcion->getUsuario();
        $ownerId = $this->normalizeComparableValue($owner->getId());
        if (null === $ownerId) {
            return false;
        }

        return null !== $this->relacionUsuarioRepository->findRelacionadoByUsuarioYRelacionadoId($user, $ownerId);
    }

    /**
     * Puede editar la inscripción si:
     * - Es admin de la entidad
     * - Es superadmin
     */
    private function canEdit(bool $hasPrivilegedRole): bool
    {
        return $hasPrivilegedRole;
    }

    /**
     * Puede eliminar la inscripción si:
     * - Es admin de la entidad
     * - Es superadmin
     */
    private function canDelete(bool $hasPrivilegedRole): bool
    {
        return $hasPrivilegedRole;
    }

    private function isOwner(Inscripcion $inscripcion, Usuario|UserInterface $user): bool
    {
        $owner = $inscripcion->getUsuario();
        $ownerId = $this->normalizeComparableValue($owner->getId());
        $userId = $this->extractUserId($user);

        if (null !== $ownerId && null !== $userId && $ownerId === $userId) {
            return true;
        }

        $ownerIdentifiers = array_filter([
            $this->normalizeIdentifier($owner->getUserIdentifier()),
            $this->normalizeIdentifier($owner->getEmail()),
        ]);

        $userIdentifiers = array_filter([
            $this->normalizeIdentifier($user->getUserIdentifier()),
            method_exists($user, 'getEmail') ? $this->normalizeIdentifier((string) $user->getEmail()) : null,
        ]);

        foreach ($userIdentifiers as $userIdentifier) {
            if (in_array($userIdentifier, $ownerIdentifiers, true)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeAttribute(string $attribute): ?string
    {
        return match (strtoupper(trim($attribute))) {
            self::VIEW => self::VIEW,
            self::EDIT => self::EDIT,
            self::DELETE => self::DELETE,
            self::LEGACY_VIEW => self::LEGACY_VIEW,
            self::LEGACY_EDIT => self::LEGACY_EDIT,
            self::LEGACY_DELETE => self::LEGACY_DELETE,
            default => null,
        };
    }

    private function extractUserId(Usuario|UserInterface $user): ?string
    {
        if ($user instanceof Usuario) {
            return $this->normalizeComparableValue($user->getId());
        }

        if (!is_callable([$user, 'getId'])) {
            return null;
        }

        /** @var mixed $id */
        $id = $user->{'getId'}();

        return $this->normalizeComparableValue($id);
    }

    private function normalizeComparableValue(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (is_scalar($value)) {
            $normalized = trim((string) $value);
            return '' === $normalized ? null : $normalized;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            $normalized = trim((string) $value);
            return '' === $normalized ? null : $normalized;
        }

        return null;
    }

    private function normalizeIdentifier(?string $identifier): ?string
    {
        if (null === $identifier) {
            return null;
        }

        $normalized = mb_strtolower(trim($identifier));

        return '' === $normalized ? null : $normalized;
    }
}
