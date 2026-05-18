<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use App\Entity\Usuario;
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

        $usuarioActual = $this->security->getUser();
        if (!$usuarioActual instanceof Usuario) {
            throw new AccessDeniedHttpException('No tienes permiso para ver estas relaciones.');
        }

        $isOwner = $usuarioActual->getId() === $usuario->getId();
        $isSuperadmin = $this->security->isGranted('ROLE_SUPERADMIN');
        $isAdminEntidad = $this->security->isGranted('ROLE_ADMIN_ENTIDAD')
            && $usuarioActual->getEntidad()->getId() === $usuario->getEntidad()->getId();

        if (!$isOwner && !$isAdminEntidad && !$isSuperadmin) {
            throw new AccessDeniedHttpException('No tienes permiso para ver estas relaciones.');
        }

        $relaciones = $this->relacionRepository->findRelacionadosByUsuario($usuario);

        // Deduplicar por par de usuarios: misma relación en ambos sentidos cuenta una sola vez
        $seen = [];
        $resultado = [];

        foreach ($relaciones as $relacion) {
            $origen = $relacion->getUsuarioOrigen();
            $destino = $relacion->getUsuarioDestino();

            if (!$origen || !$destino) {
                continue;
            }

            // No mostrar relaciones donde alguno de los dos usuarios esté inactivo o de baja
            if (
                !$origen->isActivo()
                || $origen->getFechaBajaCenso() !== null
                || !$destino->isActivo()
                || $destino->getFechaBajaCenso() !== null
            ) {
                continue;
            }

            $origenId  = $origen->getId();
            $destinoId = $destino->getId();

            $clave = implode('|', [min($origenId, $destinoId), max($origenId, $destinoId)]);

            if (isset($seen[$clave])) {
                continue;
            }

            $seen[$clave] = true;
            $resultado[] = $relacion;
        }

        return $resultado;
    }
}
