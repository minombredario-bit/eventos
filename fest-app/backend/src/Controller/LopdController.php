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
        $isAdmin = $security->isGranted('ROLE_ADMIN') || $security->isGranted('ROLE_ADMIN_ENTIDAD') || $security->isGranted('ROLE_SUPERADMIN');

        if (!$isAdmin && (string) $user->getId() !== (string) $target->getId()) {
            throw new AccessDeniedHttpException('No tienes permisos para modificar este usuario.');
        }

        // Parse JSON safely; Request::toArray() throws BadRequestHttpException on invalid JSON
        $data = $request->toArray();
        if (!is_array($data) || !array_key_exists('acepto', $data)) {
            throw new BadRequestHttpException('Campo "acepto" requerido.');
        }

        // Normalize and validate boolean-ish values
        $raw = $data['acepto'];
        if (is_bool($raw)) {
            $acepto = $raw;
        } else {
            // Accept strings/numeric like 'true','false','1','0'
            $acepto = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($acepto === null) {
                throw new BadRequestHttpException('Campo "acepto" debe ser booleano.');
            }
        }

        $target->setAceptoLopd((bool) $acepto);

        // $target is managed by the EntityManager (retrieved with find()), so only flush is needed
        $em->flush();

        $payload = $serializer->serialize($target, 'json', ['groups' => ['usuario:read', 'read_user_admin']]);

        return new JsonResponse($payload, 200, ['Content-Type' => 'application/json'], true);
    }
}

