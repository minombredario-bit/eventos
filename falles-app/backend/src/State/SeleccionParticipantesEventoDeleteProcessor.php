<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Usuario;
use App\Repository\EventoRepository;
use App\Repository\SeleccionParticipantesEventoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SeleccionParticipantesEventoDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EventoRepository $eventoRepository,
        private readonly SeleccionParticipantesEventoRepository $seleccionParticipantesEventoRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->security->getUser();

        if (!$user instanceof Usuario) {
            throw new AccessDeniedHttpException('No autenticado.');
        }

        $eventoId = is_string($uriVariables['eventoId'] ?? null) ? $uriVariables['eventoId'] : null;
        $evento = $eventoId !== null ? $this->eventoRepository->find($eventoId) : null;

        if ($evento === null) {
            throw new NotFoundHttpException('Evento no encontrado.');
        }

        if ($evento->getEntidad()->getId() !== $user->getEntidad()->getId()) {
            throw new AccessDeniedHttpException('No tienes acceso a este evento.');
        }

        $selecciones = $this->seleccionParticipantesEventoRepository->findByUsuarioAndEventoOrdered($user, $evento);

        if ($selecciones !== []) {
            foreach ($selecciones as $seleccion) {
                $this->entityManager->remove($seleccion);
            }

            $this->entityManager->flush();
        }

        return null;
    }
}
