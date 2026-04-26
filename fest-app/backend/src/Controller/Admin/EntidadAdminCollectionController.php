<?php

namespace App\Controller\Admin;

use App\Entity\Entidad;
use App\Repository\EntidadRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Controller used by API Platform for the admin `/entidad` collection endpoint.
 * Returns either all entidades for superadmins or the current user's entidad for entity admins.
 */
final class EntidadAdminCollectionController
{
    public function __construct(
        private readonly EntidadRepository $repository,
        private readonly AuthorizationCheckerInterface $authChecker,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly SerializerInterface $serializer,
    ) {
    }

    /**
     * __invoke is called by API Platform and should return an array or Traversable of Entidad.
     * @return Entidad[]|Response
     */
    public function __invoke(): JsonResponse
    {
        $token = $this->tokenStorage->getToken();
        $user = $token ? $token->getUser() : null;

        if ($this->authChecker->isGranted('ROLE_SUPERADMIN')) {
            $items = $this->repository->findAll();
        } elseif ($this->authChecker->isGranted('ROLE_ADMIN_ENTIDAD') || $this->authChecker->isGranted('ROLE_ADMIN')) {
            if (method_exists($user, 'getEntidad')) {
                $entidad = $user->getEntidad();
                if ($entidad instanceof Entidad) {
                    $items = [$entidad];
                } else {
                    $items = [];
                }
            } else {
                $items = [];
            }
        } else {
            throw new AccessDeniedException('Access denied.');
        }

        // Normalize entities to arrays using the serializer and the entidad:read group
        $data = $this->serializer->normalize($items, null, ['groups' => ['entidad:read']]);

        return new JsonResponse($data);
    }
}

