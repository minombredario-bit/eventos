<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Entity\Usuario;
use App\Enum\MetodoPagoEnum;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/api')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?Usuario $user): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse([
                'message' => 'No autenticado'
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse($this->normalizeMeResponse($user));
    }

    #[Route('/me', name: 'api_me_patch', methods: ['PATCH'])]
    public function patchMe(Request $request, #[CurrentUser] ?Usuario $user): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse([
                'message' => 'No autenticado'
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $payload = $request->toArray();

        if (array_key_exists('telefono', $payload)) {
            $telefono = $payload['telefono'];
            if ($telefono !== null && !is_string($telefono)) {
                throw new BadRequestHttpException('El campo telefono debe ser string o null.');
            }

            $user->setTelefono(is_string($telefono) ? trim($telefono) ?: null : null);
        }

        if (array_key_exists('fechaNacimiento', $payload)) {
            $fechaNacimiento = $payload['fechaNacimiento'];

            if ($fechaNacimiento === null || $fechaNacimiento === '') {
                $user->setFechaNacimiento(null);
            } elseif (is_string($fechaNacimiento)) {
                $date = \DateTimeImmutable::createFromFormat('Y-m-d', $fechaNacimiento);
                if (!$date) {
                    throw new BadRequestHttpException('El campo fechaNacimiento debe tener formato YYYY-MM-DD.');
                }
                $user->setFechaNacimiento($date);
            } else {
                throw new BadRequestHttpException('El campo fechaNacimiento debe ser string o null.');
            }
        }

        if (array_key_exists('formaPagoPreferida', $payload)) {
            $formaPagoPreferida = $payload['formaPagoPreferida'];

            if ($formaPagoPreferida === null || $formaPagoPreferida === '') {
                $user->setFormaPagoPreferida(null);
            } elseif (is_string($formaPagoPreferida)) {
                $enum = MetodoPagoEnum::tryFrom($formaPagoPreferida);
                if ($enum === null) {
                    throw new BadRequestHttpException('Forma de pago preferida inválida.');
                }
                $user->setFormaPagoPreferida($enum);
            } else {
                throw new BadRequestHttpException('El campo formaPagoPreferida debe ser string o null.');
            }
        }

        $this->entityManager->flush();

        return new JsonResponse($this->normalizeMeResponse($user));
    }

    #[Route('/me/cambiar-password', name: 'api_me_cambiar_password', methods: ['POST'])]
    public function cambiarPassword(
        Request $request,
        #[CurrentUser] ?Usuario $user,
    ): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse([
                'message' => 'No autenticado'
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $data = $request->toArray();
        $currentPassword = (string) ($data['currentPassword'] ?? '');
        $newPassword = (string) ($data['newPassword'] ?? '');

        if ($newPassword === '' || strlen($newPassword) < 8) {
            throw new BadRequestHttpException('La nueva contraseña debe tener al menos 8 caracteres.');
        }

        if (!$user->isDebeCambiarPassword() && !$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            throw new BadRequestHttpException('La contraseña actual no es válida.');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $user->setDebeCambiarPassword(false);
        $user->setPasswordActualizadaAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return new JsonResponse([
            'ok' => true,
            'token' => $this->jwtManager->create($user),
            'user' => $this->normalizeMeResponse($user),
        ]);
    }

    /**
     * Normaliza la respuesta del usuario para los endpoints /api/me.
     * FIX: incluidos todos los campos que el frontend (AuthStore) espera encontrar,
     * especialmente roles, nombreEntidad, tipoEntidad y aceptoLopd que faltaban
     * y se perdían al hacer getMe() → merge en el store.
     *
     * @return array<string, mixed>
     */
    private function normalizeMeResponse(Usuario $user): array
    {
        return [
            'id'                  => $user->getId(),
            'email'               => $user->getEmail(),
            'nombre'              => $user->getNombre(),
            'apellidos'           => $user->getApellidos(),
            'nombreCompleto'      => $user->getNombreCompleto(),
            'telefono'            => $user->getTelefono(),
            'fechaNacimiento'     => $user->getFechaNacimiento()?->format('Y-m-d'),
            'formaPagoPreferida'  => $user->getFormaPagoPreferida()?->value,
            'debeCambiarPassword' => $user->isDebeCambiarPassword(),
            'tipoUsuarioEconomico'=> $user->getTipoUsuarioEconomico()->value,
            'roles'               => $user->getRoles(),
            'nombreEntidad'       => $user->getEntidad()->getNombre(),
            'tipoEntidad'         => mb_strtolower($user->getEntidad()->getTipoEntidad()?->getNombre() ?? ''),
            'aceptoLopd'          => $user->isAceptoLopd(),
            'aceptoLopdAt'        => $user->getAceptoLopdAt()?->format(DATE_ATOM),
            'antiguedad'          => $user->getAntiguedad(),
            'antiguedadReal'      => $user->getAntiguedadReal(),
        ];
    }
}
