<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Usuario;
use App\Repository\EventoRepository;
use App\Repository\SeleccionParticipanteEventoRepository;
use App\Repository\InscripcionRepository;
use App\Service\EmailQueueService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SeleccionParticipantesEventoDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EventoRepository $eventoRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SeleccionParticipanteEventoRepository $seleccionParticipanteEventoRepository,
        private readonly InscripcionRepository $inscripcionRepository,
        private readonly EmailQueueService $emailQueueService,
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

        $seleccionesGranulares = $this->seleccionParticipanteEventoRepository->findByEventoAndInscritoPorUsuario($evento, $user);
        foreach ($seleccionesGranulares as $seleccionGranular) {
            $this->entityManager->remove($seleccionGranular);
        }

        $inscripcionTitular = $this->inscripcionRepository->findOneByUsuarioAndEvento($user->getId(), $evento->getId());
        if ($inscripcionTitular !== null) {
            $this->emailQueueService->enqueueInscripcionCambio($inscripcionTitular, 'borrado');
        }

        $this->entityManager->flush();

        return null;
    }
}
