<?php

namespace App\Controller;

use App\Entity\Usuario;
use App\Enum\MetodoPagoEnum;
use App\Repository\UsuarioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class RegistroController extends AbstractController
{
    public function __construct(
        private readonly UsuarioRepository $usuarioRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    #[Route('/registro/validar-codigo', name: 'api_registro_validar_codigo', methods: ['POST'])]
    public function validarCodigo(Request $request): JsonResponse
    {
        return $this->json([
            'error' => 'El auto-registro está deshabilitado. Contacta con el administrador de tu entidad.',
        ], Response::HTTP_GONE);
    }

    #[Route('/registro/solicitud', name: 'api_registro_solicitud', methods: ['POST'])]
    public function solicitud(Request $request): JsonResponse
    {
        return $this->json([
            'error' => 'El alta de usuarios solo puede realizarla el administrador de la entidad.',
        ], Response::HTTP_FORBIDDEN);
    }

    /**
     * Get current user profile.
     */
    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var Usuario $user */
        $user = $this->getUser();

        return $this->json([
            'id' => $user->getId(),
            'nombre' => $user->getNombre(),
            'apellidos' => $user->getApellidos(),
            'email' => $user->getEmail(),
            'telefono' => $user->getTelefono(),
            'roles' => $user->getRoles(),
            'entidad' => '/api/entidads/' . $user->getEntidad()->getId(),
            'tipoUsuarioEconomico' => $user->getTipoUsuarioEconomico()->value,
            'estadoValidacion' => $user->getEstadoValidacion()->value,
            'esCensadoInterno' => $user->isEsCensadoInterno(),
            'censadoVia' => $user->getCensadoVia()?->value,
            'formaPagoPreferida' => $user->getFormaPagoPreferida()?->value,
            'antiguedad' => $user->getAntiguedad(),
            'antiguedadReal' => $user->getAntiguedadReal(),
            'debeCambiarPassword' => $user->isDebeCambiarPassword(),
            'puedeAcceder' => $user->puedeAcceder(),
            'fechaSolicitudAlta' => $user->getFechaSolicitudAlta()?->format('c'),
            'fechaValidacion' => $user->getFechaValidacion()?->format('c'),
        ]);
    }

    #[Route('/me/cambiar-password', name: 'api_me_cambiar_password', methods: ['POST'])]
    public function cambiarPassword(Request $request): JsonResponse
    {
        /** @var Usuario $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        $currentPassword = (string) ($data['currentPassword'] ?? '');
        $newPassword = (string) ($data['newPassword'] ?? '');

        if ($newPassword === '' || strlen($newPassword) < 8) {
            return $this->json(['error' => 'La nueva contraseña debe tener al menos 8 caracteres'], Response::HTTP_BAD_REQUEST);
        }

        if (!$user->isDebeCambiarPassword() && !$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            return $this->json(['error' => 'La contraseña actual no es válida'], Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $user->setDebeCambiarPassword(false);
        $user->setPasswordActualizadaAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->json(['ok' => true]);
    }

    #[Route('/me', name: 'api_me_patch', methods: ['PATCH'])]
    public function updateMe(Request $request): JsonResponse
    {
        /** @var Usuario $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (isset($data['email'])) {
            $email = strtolower(trim((string) $data['email']));
            $existing = $this->usuarioRepository->findByEmail($email);
            if ($existing instanceof Usuario && $existing->getId() !== $user->getId()) {
                return $this->json(['error' => 'El email ya está registrado'], Response::HTTP_CONFLICT);
            }
            $user->setEmail($email);
        }
        if (isset($data['telefono'])) {
            $user->setTelefono($data['telefono']);
        }
        if (array_key_exists('formaPagoPreferida', $data)) {
            $user->setFormaPagoPreferida(
                $data['formaPagoPreferida'] !== null
                    ? MetodoPagoEnum::from((string) $data['formaPagoPreferida'])
                    : null
            );
        }

        $this->entityManager->flush();

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'telefono' => $user->getTelefono(),
            'formaPagoPreferida' => $user->getFormaPagoPreferida()?->value,
        ]);
    }
}
