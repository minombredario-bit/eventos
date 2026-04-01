<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Invitado;
use App\Repository\InvitadoRepository;
use App\Repository\SeleccionParticipantesEventoRepository;
use Doctrine\ORM\EntityManagerInterface;

class InvitadoDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InvitadoRepository $invitadoRepository,
        private readonly SeleccionParticipantesEventoRepository $seleccionParticipantesEventoRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Invitado) {
            return null;
        }

        if ($data->isDeleted()) {
            return null;
        }

        $data->setDeletedAt(new \DateTimeImmutable());

        $householdUserIds = $this->invitadoRepository->resolveHouseholdUserIds($data->getCreadoPor());
        $selecciones = $this->seleccionParticipantesEventoRepository
            ->findByUsuarioIdsAndEvento($householdUserIds, $data->getEvento());

        $invitadoId = (string) $data->getId();

        foreach ($selecciones as $seleccion) {
            $participantes = [];

            foreach ($seleccion->getParticipantes() as $participante) {
                if (!is_array($participante)) {
                    continue;
                }

                $origen = $this->normalizeOrigen($participante['origen'] ?? null);
                $participanteId = $this->normalizeParticipanteId($participante['id'] ?? null);

                if ($origen === 'invitado' && $participanteId === $invitadoId) {
                    continue;
                }

                if ($participanteId === '') {
                    continue;
                }

                $participantes[] = [
                    'id' => $participanteId,
                    'origen' => $origen,
                ];
            }

            $seleccion->setParticipantes($participantes);
        }

        $this->entityManager->flush();

        return null;
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
