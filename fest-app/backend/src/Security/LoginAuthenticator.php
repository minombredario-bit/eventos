<?php

namespace App\Security;

use App\Entity\Usuario;
use App\Repository\UsuarioRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\Response;

class LoginAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private UsuarioRepository $repo,
        private UserPasswordHasherInterface $hasher
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->getPathInfo() === '/api/login' && $request->isMethod('POST');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $data = json_decode($request->getContent(), true);

        $identifier = trim($data['identificador'] ?? '');
        $password = $data['password'] ?? '';

        if (!$identifier || !$password) {
            throw new CustomUserMessageAuthenticationException('Credenciales inválidas');
        }

        $usuarios = $this->repo->createQueryBuilder('u')
            ->andWhere('u.activo = 1')
            ->andWhere('u.fechaBajaCenso IS NULL')
            ->andWhere('LOWER(u.email) = LOWER(:id) OR UPPER(u.documentoIdentidad) = UPPER(:id)')
            ->setParameter('id', $identifier)
            ->getQuery()
            ->getResult();

        if (!$usuarios) {
            throw new CustomUserMessageAuthenticationException('Usuario no encontrado');
        }

        foreach ($usuarios as $usuario) {
            if ($this->hasher->isPasswordValid($usuario, $password)) {
                return new SelfValidatingPassport(
                    new UserBadge($usuario->getUserIdentifier(), fn() => $usuario)
                );
            }
        }

        throw new CustomUserMessageAuthenticationException('Contraseña incorrecta');
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // JWT continúa
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'message' => $exception->getMessage()
        ], 401);
    }
}
