<?php

namespace App\Controller\Api;

use App\Service\PasskeyService;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\PasskeyCredentialRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Entity\Usuario;

#[Route('/api/auth/passkey')]
class PasskeyController extends AbstractController
{
    public function __construct(
        private readonly PasskeyService $passkeyService,
        private readonly PasskeyCredentialRepository $credentialRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    // ── Registration ──────────────────────────────────────────────────────

    #[Route('/register/options', name: 'api_passkey_register_options', methods: ['GET'])]
    public function registerOptions(#[CurrentUser] ?Usuario $user): JsonResponse
    {
        if ($user === null) {
            return new JsonResponse(['message' => 'No autenticado'], 401);
        }

        $options = $this->passkeyService->generateRegistrationOptions($user);

        return new JsonResponse($options);
    }

    #[Route('/register', name: 'api_passkey_register', methods: ['POST'])]
    public function register(Request $request, #[CurrentUser] ?Usuario $user): JsonResponse
    {
        if ($user === null) {
            return new JsonResponse(['message' => 'No autenticado'], 401);
        }

        $data = $request->toArray();

        $credentialId        = (string) ($data['id'] ?? '');
        $attestationObjectB64 = (string) ($data['response']['attestationObject'] ?? '');
        $clientDataJsonB64    = (string) ($data['response']['clientDataJSON'] ?? '');
        $deviceName           = (string) ($data['deviceName'] ?? 'Dispositivo');

        if ($credentialId === '' || $attestationObjectB64 === '' || $clientDataJsonB64 === '') {
            throw new BadRequestHttpException('Datos de registro incompletos.');
        }

        try {
            $credential = $this->passkeyService->verifyRegistration(
                $user,
                $credentialId,
                $attestationObjectB64,
                $clientDataJsonB64,
                $deviceName,
            );
        } catch (\JsonException $e) {
            throw new BadRequestHttpException('Datos JSON inválidos: ' . $e->getMessage());
        }

        return new JsonResponse([
            'ok'         => true,
            'id'         => $credential->getId(),
            'deviceName' => $credential->getDeviceName(),
            'createdAt'  => $credential->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], 201);
    }

    // ── Credentials list / delete ─────────────────────────────────────────

    #[Route('/credentials', name: 'api_passkey_credentials', methods: ['GET'])]
    public function listCredentials(#[CurrentUser] ?Usuario $user): JsonResponse
    {
        if ($user === null) {
            return new JsonResponse(['message' => 'No autenticado'], 401);
        }

        $credentials = $this->credentialRepository->findByUsuario($user);

        return new JsonResponse(array_map(
            fn($c) => [
                'id'         => $c->getId(),
                'deviceName' => $c->getDeviceName(),
                'createdAt'  => $c->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'lastUsedAt' => $c->getLastUsedAt()?->format(\DateTimeInterface::ATOM),
            ],
            $credentials,
        ));
    }

    #[Route('/credentials/{id}', name: 'api_passkey_credential_delete', methods: ['DELETE'])]
    public function deleteCredential(string $id, #[CurrentUser] ?Usuario $user): JsonResponse
    {
        if ($user === null) {
            return new JsonResponse(['message' => 'No autenticado'], 401);
        }

        $credential = $this->credentialRepository->find($id);

        if ($credential === null || $credential->getUsuario()->getId() !== $user->getId()) {
            return new JsonResponse(['message' => 'Credencial no encontrada'], 404);
        }

        $this->em->remove($credential);
        $this->em->flush();

        return new JsonResponse(['ok' => true]);
    }

    // ── Authentication ────────────────────────────────────────────────────

    #[Route('/login/options', name: 'api_passkey_login_options', methods: ['POST'])]
    public function loginOptions(Request $request): JsonResponse
    {
        $data  = $request->toArray();
        $email = isset($data['email']) ? trim((string) $data['email']) : null;

        $options = $this->passkeyService->generateAuthenticationOptions($email ?: null);

        return new JsonResponse($options);
    }

    #[Route('/login', name: 'api_passkey_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = $request->toArray();

        $credentialId          = (string) ($data['id'] ?? '');
        $authenticatorDataB64  = (string) ($data['response']['authenticatorData'] ?? '');
        $clientDataJsonB64     = (string) ($data['response']['clientDataJSON'] ?? '');
        $signatureB64          = (string) ($data['response']['signature'] ?? '');
        $challengeCacheKey     = (string) ($data['_cacheKey'] ?? '');

        if ($credentialId === '' || $authenticatorDataB64 === '' || $clientDataJsonB64 === ''
            || $signatureB64 === '' || $challengeCacheKey === '') {
            throw new BadRequestHttpException('Datos de autenticación biométrica incompletos.');
        }

        try {
            $token = $this->passkeyService->verifyAuthentication(
                $credentialId,
                $authenticatorDataB64,
                $clientDataJsonB64,
                $signatureB64,
                $challengeCacheKey,
            );
        } catch (\JsonException $e) {
            error_log('❌ Passkey login error: ' . $e->getMessage());
            error_log('❌ ' . $e->getTraceAsString());
            throw new BadRequestHttpException('Datos JSON inválidos: ' . $e->getMessage());
        }

        return new JsonResponse(['token' => $token]);
    }
}

