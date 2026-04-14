<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/registro')]
class RegistroController extends AbstractController
{

    /**
     * Valida un código de entidad y devuelve su información.
     */
    #[Route('/validar-codigo', name: 'registro_validar_codigo', methods: ['POST'])]
    public function validarCodigo(Request $request): JsonResponse
    {
        return new JsonResponse([
            'error' => 'El auto-registro está deshabilitado. Contacta con el administrador de la entidad.',
        ], 410);
    }

    /**
     * Solicita el registro de un nuevo usuario.
     */
    #[Route('/solicitud', name: 'registro_solicitud', methods: ['POST'])]
    public function solicitud(Request $request): JsonResponse
    {
        return new JsonResponse([
            'error' => 'El alta de usuarios solo la puede hacer el administrador de la entidad.',
        ], 403);
    }
}
