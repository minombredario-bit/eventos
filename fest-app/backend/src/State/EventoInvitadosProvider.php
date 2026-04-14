<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\InvitadoView;
use App\Entity\Usuario;
use App\Repository\EventoRepository;
use App\Repository\InvitadoRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EventoInvitadosProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EventoRepository $eventoRepository,
        private readonly InvitadoRepository $invitadoRepository,
    ) {
    }

    /**
     * @return list<InvitadoView>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();

        if (!$user instanceof Usuario) {
            throw new AccessDeniedHttpException('No autenticado.');
        }

        $eventoId = is_string($uriVariables['id'] ?? null) ? $uriVariables['id'] : null;
        $evento = $eventoId !== null ? $this->eventoRepository->find($eventoId) : null;

        if ($evento === null) {
            throw new NotFoundHttpException('Evento no encontrado.');
        }

        if ($evento->getEntidad()->getId() !== $user->getEntidad()->getId()) {
            throw new AccessDeniedHttpException('No tienes acceso a este evento.');
        }

        if (!$evento->permiteGestionInvitadosConActividades()) {
            return [];
        }

        $invitados = $this->invitadoRepository->findByEventoAndHouseholdUsers($evento, $user);
        $items = [];

        foreach ($invitados as $invitado) {
            $item = new InvitadoView();
            $item->id = $invitado->getId();
            $item->nombre = $invitado->getNombre();
            $item->apellidos = $invitado->getApellidos();
            $item->nombreCompleto = $invitado->getNombreCompleto();
            $item->tipoPersona = $invitado->getTipoPersona()->value;
            $item->observaciones = $invitado->getObservaciones();
            $item->iri = '/api/invitados/' . $invitado->getId();
            $items[] = $item;
        }

        return $items;
    }
}
