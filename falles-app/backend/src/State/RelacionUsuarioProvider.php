<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\RelacionUsuarioRepository;
use App\Repository\UsuarioRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RelacionUsuarioProvider implements ProviderInterface
{
    public function __construct(
        private readonly RelacionUsuarioRepository $relacionRepository,
        private readonly UsuarioRepository $usuarioRepository,
        private readonly Security $security,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $usuarioId = $uriVariables['id'] ?? null;

        $usuario = $this->usuarioRepository->find($usuarioId);

        if (!$usuario) {
            throw new NotFoundHttpException('Usuario no encontrado.');
        }

        // Solo el propio usuario o un admin puede ver sus relaciones
        $usuarioActual = $this->security->getUser();
        if (
            $usuarioActual !== $usuario &&
            !$this->security->isGranted('ROLE_ADMIN')
        ) {
            throw new AccessDeniedHttpException('No tienes permiso para ver estas relaciones.');
        }

        return $this->relacionRepository->findRelacionadosByUsuario($usuario);
    }
}