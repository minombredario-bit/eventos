<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\RelacionUsuario;
use App\Entity\Usuario;
use App\Repository\UsuarioRepository;
use Symfony\Bundle\SecurityBundle\Security;

class RelacionUsuarioProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly UsuarioRepository $usuarioRepository,
        private readonly Security $security,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RelacionUsuario
    {
        $relacion = new RelacionUsuario();

        // El usuario origen viene de la URL
        $usuarioOrigenId = $uriVariables['id'] ?? null;
        $usuarioOrigen = $this->usuarioRepository->find($usuarioOrigenId);

        if (!$usuarioOrigen) {
            throw new \Exception('Usuario origen no encontrado');
        }

        // El usuario destino viene del body
        $usuarioDestinoIri = $data->usuarioDestino ?? null;
        if ($usuarioDestinoIri) {
            $usuarioDestinoId = basename($usuarioDestinoIri);
            $usuarioDestino = $this->usuarioRepository->find($usuarioDestinoId);
        } else {
            $usuarioDestino = null;
        }

        if (!$usuarioDestino) {
            throw new \Exception('Usuario destino no encontrado');
        }

        // Verificar permisos
        $usuarioActual = $this->security->getUser();
        if ($usuarioActual !== $usuarioOrigen && !$this->security->isGranted('ROLE_ADMIN')) {
            throw new \Exception('No tienes permiso para crear relaciones para este usuario');
        }

        $relacion->setUsuarioOrigen($usuarioOrigen);
        $relacion->setUsuarioDestino($usuarioDestino);
        $relacion->setTipoRelacion($data->tipoRelacion);

        $entityManager = $this->usuarioRepository->getEntityManager();
        $entityManager->persist($relacion);
        $entityManager->flush();

        return $relacion;
    }
}
