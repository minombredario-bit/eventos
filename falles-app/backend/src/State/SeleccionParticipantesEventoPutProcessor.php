<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\SeleccionParticipantesInput;
use App\Dto\SeleccionParticipantesView;
use App\Entity\SeleccionParticipantesEvento;
use App\Entity\Usuario;
use App\Repository\EventoRepository;
use App\Repository\SeleccionParticipantesEventoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SeleccionParticipantesEventoPutProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EventoRepository $eventoRepository,
        private readonly SeleccionParticipantesEventoRepository $seleccionParticipantesEventoRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SeleccionParticipantesView
    {
        if (!$data instanceof SeleccionParticipantesInput) {
            throw new BadRequestHttpException('Payload inválido.');
        }

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

        $participantes = [];
        foreach ($data->participantes as $index => $participante) {
            if (!is_array($participante)) {
                throw new BadRequestHttpException(sprintf('Participante inválido en índice %d', $index));
            }

            $origen = $participante['origen'] ?? null;
            $participanteId = $this->normalizeParticipanteId($participante['id'] ?? null);

            if (!is_string($origen) || !in_array($origen, ['familiar', 'invitado'], true)) {
                throw new BadRequestHttpException(sprintf('Origen inválido en índice %d', $index));
            }

            if ($participanteId === '') {
                throw new BadRequestHttpException(sprintf('ID de participante inválido en índice %d', $index));
            }

            $participantes[] = [
                'id' => $participanteId,
                'origen' => $this->normalizeOrigen($origen),
            ];
        }

        $selecciones = $this->seleccionParticipantesEventoRepository
            ->findByUsuarioAndEventoOrdered($user, $evento);

        $seleccion = $selecciones[0] ?? null;

        if (count($selecciones) > 1) {
            foreach (array_slice($selecciones, 1) as $duplicada) {
                $this->entityManager->remove($duplicada);
            }
        }

        if ($seleccion === null) {
            $seleccion = new SeleccionParticipantesEvento();
            $seleccion->setUsuario($user);
            $seleccion->setEvento($evento);
            $this->entityManager->persist($seleccion);
        }

        $seleccion->setParticipantes($participantes);
        $this->entityManager->flush();

        $response = new SeleccionParticipantesView();
        $response->eventoId = $evento->getId();
        $response->participantes = $seleccion->getParticipantes();
        $response->updatedAt = $seleccion->getUpdatedAt()->format('c');

        return $response;
    }

    private function normalizeParticipanteId(mixed $rawId): string
    {
        if (!is_string($rawId)) {
            return '';
        }

        $cleaned = trim($rawId);
        if ($cleaned === '') {
            return '';
        }

        if (!str_contains($cleaned, '/')) {
            return $cleaned;
        }

        $parts = array_values(array_filter(explode('/', trim($cleaned, '/'))));

        return $parts === [] ? '' : (string) end($parts);
    }

    private function normalizeOrigen(mixed $origen): string
    {
        if ($origen === 'invitado') {
            return 'invitado';
        }

        return 'familiar';
    }
}
