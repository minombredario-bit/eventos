<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\InvitadoRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InvitadoProvider implements ProviderInterface
{
    public function __construct(
        private readonly InvitadoRepository $invitadoRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $id = is_string($uriVariables['id'] ?? null) ? trim($uriVariables['id']) : '';

        if ($id !== '') {
            $invitado = $this->invitadoRepository->findActiveById($id);

            if ($invitado === null) {
                throw new NotFoundHttpException('Invitado no encontrado.');
            }

            return $invitado;
        }

        return $this->invitadoRepository->findActiveAllOrderedByCreatedAtDesc();
    }
}
