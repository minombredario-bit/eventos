<?php

namespace App\Controller;

use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\SerializerInterface;

final class LopdController extends AbstractController
{
    #[Route('/api/usuarios/{id}/lopd', name: 'api_usuario_lopd', methods: ['PATCH'])]
    public function patchLopd(
        string $id,
        Request $request,
        Security $security,
        EntityManagerInterface $em,
        SerializerInterface $serializer
    ): JsonResponse {
        $user = $security->getUser();
        if (!$user instanceof Usuario) {
            throw new AccessDeniedHttpException('Usuario no autenticado.');
        }

        /** @var Usuario|null $target */
        $target = $em->find(Usuario::class, $id);
        if (!$target instanceof Usuario) {
            throw new BadRequestHttpException('Usuario no encontrado.');
        }

        // Allow user to update their own acceptance, or admins to update any
        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true)
            || in_array('ROLE_ADMIN_ENTIDAD', $user->getRoles(), true)
            || in_array('ROLE_SUPERADMIN', $user->getRoles(), true);

        if (!$isAdmin && (string)$user->getId() !== (string)$target->getId()) {
            throw new AccessDeniedHttpException('No tienes permisos para modificar este usuario.');
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !array_key_exists('acepto', $data)) {
            throw new BadRequestHttpException('Campo "acepto" requerido.');
        }

        $acepto = (bool) $data['acepto'];

        $target->setAceptoLopd($acepto);

        $em->persist($target);
        $em->flush();

        $payload = $serializer->serialize($target, 'json', ['groups' => ['usuario:read', 'read_user_admin']]);

        return new JsonResponse($payload, 200, ['Content-Type' => 'application/json'], true);
    }
}

