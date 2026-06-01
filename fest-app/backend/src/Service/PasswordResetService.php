<?php

namespace App\Service;

use App\Entity\PasswordResetToken;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UsuarioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PasswordResetService
{
    private const TOKEN_TTL_MINUTES = 60;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PasswordResetTokenRepository $tokenRepository,
        private readonly UsuarioRepository $usuarioRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EmailQueueService $emailQueueService,
        private readonly string $appUri = '',
    ) {}

    /**
     * Generates a password reset token and enqueues the email.
     * If user has email: send recovery link to user
     * If user has no email: send notification to entity admins
     * Always completes silently to avoid user enumeration.
     */
    public function requestReset(string $email): void
    {
        $email = mb_strtolower(trim($email));

        $this->tokenRepository->deleteByEmail($email);

        $usuario = $this->usuarioRepository->findOneBy(['email' => $email]);

        if ($usuario === null || !$usuario->isActivo()) {
            return;
        }

        $tokenString = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable(sprintf('+%d minutes', self::TOKEN_TTL_MINUTES));

        $token = new PasswordResetToken($tokenString, $email, $expiresAt);
        $this->em->persist($token);

        $this->emailQueueService->enqueue(
            $email,
            'Recuperación de contraseña',
            'email/password_reset.html.twig',
            [
                'nombre' => $usuario->getNombre(),
                'resetUrl' => rtrim($this->appUri, '/') . '/auth/reset-password?token=' . $tokenString,
                'expiresInMinutes' => self::TOKEN_TTL_MINUTES,
            ],
            $usuario->getEntidad(),
            $usuario,
        );

        $this->em->flush();
    }

    /**
     * Request password reset by email or document (DNI/CIF).
     * If user has email: send recovery link to user
     * If user has no email: notify entity admins to reset manually
     * Returns true if user found, false otherwise (but silent for enumeration prevention)
     */
    public function requestResetByEmailOrDocument(string $identifier): bool
    {
        $identifier = mb_strtolower(trim($identifier));

        // Try to find by email first
        $usuario = $this->usuarioRepository->findOneBy(['email' => $identifier]);

        // If not found by email, try by document (DNI/CIF)
        if ($usuario === null) {
            $usuario = $this->usuarioRepository->findOneBy(['documentoIdentidad' => $identifier]);
        }

        // Elimina la comprobación de entidadId
        if ($usuario === null || !$usuario->isActivo() || !$usuario->getEntidad()->isActiva()) {
            return false;
        }

        // Delete old tokens for this usuario
        if ($usuario->getEmail()) {
            $this->tokenRepository->deleteByEmail($usuario->getEmail());
        }

        $tokenString = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable(sprintf('+%d minutes', self::TOKEN_TTL_MINUTES));

        $token = new PasswordResetToken($tokenString, $usuario->getEmail() ?? 'no-email-' . $usuario->getId(), $expiresAt);
        $this->em->persist($token);

        if ($usuario->getEmail()) {
            // User has email: send recovery link directly
            $this->emailQueueService->enqueue(
                $usuario->getEmail(),
                'Recuperación de contraseña',
                'email/password_reset.html.twig',
                [
                    'nombre' => $usuario->getNombre(),
                    'resetUrl' => rtrim($this->appUri, '/') . '/auth/reset-password?token=' . $tokenString,
                    'expiresInMinutes' => self::TOKEN_TTL_MINUTES,
                ],
                $usuario->getEntidad(),
                $usuario,
            );
        } else {
            // User has no email: notify entity admins
            $this->notifyAdminsForPasswordReset($usuario, $tokenString);
        }

        $this->em->flush();
        return true;
    }

    /**
     * Notify entity admins that a user without email requested password reset
     */
    private function notifyAdminsForPasswordReset(\App\Entity\Usuario $usuario, string $tokenString): void
    {
        $entidad = $usuario->getEntidad();
        $adminEmails = [];

        // Get all users with ROLE_ADMIN_ENTIDAD from the same entity
        foreach ($entidad->getUsuarios() as $usuarioEntidad) {
            $adminEmail = is_string($usuarioEntidad->getEmail())
                ? strtolower(trim($usuarioEntidad->getEmail()))
                : '';

            if ($adminEmail !== '' && in_array('ROLE_ADMIN_ENTIDAD', $usuarioEntidad->getRoles(), true)) {
                $adminEmails[] = $adminEmail;
            }
        }

        $adminEmails = array_values(array_unique($adminEmails));

        if (empty($adminEmails)) {
            // If no admin emails found, use the entity contact email
            $contactEmail = is_string($entidad->getEmailContacto())
                ? strtolower(trim($entidad->getEmailContacto()))
                : '';

            if ($contactEmail !== '') {
                $adminEmails[] = $contactEmail;
            } else {
                // If no contact email either, silently return
                return;
            }
        }

        foreach ($adminEmails as $adminEmail) {
            $this->emailQueueService->enqueue(
                $adminEmail,
                'Solicitud de restablecimiento de contraseña - Usuario sin email',
                'email/password_reset_admin_notification.html.twig',
                [
                    'usuarioNombre' => $usuario->getNombre(),
                    'usuarioApellidos' => $usuario->getApellidos(),
                    'usuarioEmail' => $usuario->getEmail() ?? 'No disponible',
                    'usuarioDocumento' => $usuario->getDocumentoIdentidad() ?? 'No disponible',
                    'entidadNombre' => $entidad->getNombre(),
                    'instrucciones' => 'Este usuario no tiene email registrado. Por favor, establece una contraseña temporal o ayúdale a crear una nueva contraseña.',
                ],
                $entidad,
            );
        }
    }

     /**
      * Validates token and sets new password.
      *
      * @throws \InvalidArgumentException if token is invalid, expired, or password too short
      */
     public function resetPassword(string $tokenString, string $newPassword): void
     {
         if (strlen($newPassword) < 8) {
             throw new \InvalidArgumentException('La contraseña debe tener al menos 8 caracteres.');
         }

         $token = $this->tokenRepository->findValidByToken($tokenString);

         if ($token === null) {
             throw new \InvalidArgumentException('El enlace de recuperación no es válido o ha expirado.');
         }

         $tokenEmail = $token->getEmail();

         // Check if this is a special token for users without email
         if (str_starts_with($tokenEmail, 'no-email-')) {
             // Extract usuario ID from token email
             $usuarioId = substr($tokenEmail, 9); // Remove 'no-email-' prefix
             $usuario = $this->usuarioRepository->find($usuarioId);
         } else {
             // Regular token with email
             $usuario = $this->usuarioRepository->findOneBy(['email' => $tokenEmail]);
         }

         if ($usuario === null || !$usuario->isActivo()) {
             throw new \InvalidArgumentException('No se pudo restablecer la contraseña.');
         }

         $usuario->setPassword($this->passwordHasher->hashPassword($usuario, $newPassword));
         $usuario->setDebeCambiarPassword(false);
         $usuario->setPasswordActualizadaAt(new \DateTimeImmutable());

         $token->markAsUsed();

         $this->em->flush();
     }
}

