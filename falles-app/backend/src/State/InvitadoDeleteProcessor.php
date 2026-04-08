<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Invitado;
use App\Repository\InvitadoRepository;
use App\Repository\SeleccionParticipanteEventoRepository;
use Doctrine\ORM\EntityManagerInterface;

class InvitadoDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InvitadoRepository $invitadoRepository,
        private readonly SeleccionParticipanteEventoRepository $seleccionParticipanteEventoRepository,
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
        $selecciones = $this->seleccionParticipanteEventoRepository
            ->findByEventoAndInvitadoAndInscritoPorUsuarioIds($data->getEvento(), $data, $householdUserIds);

        foreach ($selecciones as $seleccion) {
            $this->entityManager->remove($seleccion);
        }

        $this->entityManager->flush();

        return null;
    }
}
