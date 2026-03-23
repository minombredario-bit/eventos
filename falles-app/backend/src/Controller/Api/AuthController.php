<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Entity\Usuario;

class AuthController extends AbstractController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(#[CurrentUser] ?Usuario $user): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse([
                'message' => 'Credenciales inválidas'
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $token = $this->container->get('request_stack')->getCurrentRequest()->attributes->get('jwt_token');
        
        return new JsonResponse([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'nombre' => $user->getNombre(),
                'apellidos' => $user->getApellidos(),
            ],
        ]);
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?Usuario $user): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse([
                'message' => 'No autenticado'
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'nombre' => $user->getNombre(),
            'apellidos' => $user->getApellidos(),
            'roles' => $user->getRoles(),
            'entidad' => $user->getEntidad()?->getId(),
            'tipoUsuarioEconomico' => $user->getTipoUsuarioEconomico()->value,
            'estadoValidacion' => $user->getEstadoValidacion()->value,
        ]);
    }
}
