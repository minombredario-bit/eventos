<?php

namespace App\Controller\Api;

use App\Service\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class PasswordResetController extends AbstractController
{
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
    ) {}

    /**
     * Request a password reset link. Always returns 200 to avoid user enumeration.
     */
    #[Route('/forgot-password', name: 'api_auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $email = trim((string) ($data['email'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestHttpException('El email no es válido.');
        }

        $this->passwordResetService->requestReset($email);

        return new JsonResponse([
            'ok' => true,
            'message' => 'Si el email existe, recibirás un enlace de recuperación en breve.',
        ]);
    }

    /**
     * Reset password using a valid token.
     */
    #[Route('/reset-password', name: 'api_auth_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $token = trim((string) ($data['token'] ?? ''));
        $newPassword = (string) ($data['newPassword'] ?? '');

        if ($token === '') {
            throw new BadRequestHttpException('El token es obligatorio.');
        }

        try {
            $this->passwordResetService->resetPassword($token, $newPassword);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return new JsonResponse([
            'ok' => true,
            'message' => 'Contraseña restablecida correctamente. Ya puedes iniciar sesión.',
        ]);
    }
}

