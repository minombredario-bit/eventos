<?php

namespace App\Service;

use App\Entity\Evento;
use App\Repository\PushSubscriptionRepository;
use Symfony\Bundle\SecurityBundle\Security;

final class EventoPushNotifier
{
    public function __construct(
        private readonly PushSubscriptionRepository $pushSubscriptionRepository,
        private readonly PushNotificationService $pushNotificationService,
        private readonly Security $security,
    ) {
    }

    public function notifyEventoCreado(Evento $evento): void
    {
        $this->notifyEvento(
            $evento,
            'Nuevo evento disponible',
            sprintf('Ya puedes consultar el evento "%s".', $this->getEventoTitulo($evento)),
            sprintf('/eventos/detalle/%s', $evento->getId())
        );
    }

    public function notifyInscripcionesAbiertas(Evento $evento): void
    {
        $this->notifyEvento(
            $evento,
            'Inscripciones abiertas',
            sprintf('Ya puedes inscribirte en "%s".', $this->getEventoTitulo($evento)),
            sprintf('/eventos/detalle/%s', $evento->getId())
        );
    }

    public function notifyInscripcionesCerradas(Evento $evento): void
    {
        $this->notifyEvento(
            $evento,
            'Inscripciones cerradas',
            sprintf('Se han cerrado las inscripciones de "%s".', $this->getEventoTitulo($evento)),
            sprintf('/eventos/detalle/%s', $evento->getId())
        );
    }

    public function notifyEventoCancelado(Evento $evento): void
    {
        $this->notifyEvento(
            $evento,
            'Evento cancelado',
            sprintf('Se ha cancelado el evento "%s".', $this->getEventoTitulo($evento)),
            sprintf('/eventos/detalle/%s', $evento->getId())
        );
    }

    private function notifyEvento(Evento $evento, string $title, string $body, string $url): void
    {
        $user = $this->security->getUser();

        if (!$user || !method_exists($user, 'getEntidad')) {
            return;
        }

        $entidad = $user->getEntidad();

        if (!$entidad || !method_exists($entidad, 'getId')) {
            return;
        }

        $entidadId = $entidad->getId();

        if ($entidadId === null) {
            return;
        }

        $subscriptions = $this->pushSubscriptionRepository->findByEntidadId($entidadId);

        foreach ($subscriptions as $subscription) {
            $this->pushNotificationService->send(
                $subscription,
                $title,
                $body,
                $url
            );
        }
    }

    private function getEventoTitulo(Evento $evento): string
    {
        if (method_exists($evento, 'getTitulo') && $evento->getTitulo()) {
            return (string) $evento->getTitulo();
        }

        if (method_exists($evento, 'getNombre') && $evento->getNombre()) {
            return (string) $evento->getNombre();
        }

        return 'evento';
    }
}
