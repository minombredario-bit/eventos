<?php

namespace App\Service;

use App\Entity\Evento;
use App\Repository\PushSubscriptionRepository;

final class EventoPushNotifier
{
    public function __construct(
        private readonly PushSubscriptionRepository $pushSubscriptionRepository,
        private readonly PushNotificationService $pushNotificationService,
    ) {
    }

    public function notifyEventoCreado(Evento $evento): void
    {
        $body = sprintf(
            '%s 📅 %s%s%s',
            $this->getEventoTitulo($evento),
            $this->formatFecha($evento),
            $this->formatHora($evento),
            $this->formatLugar($evento)
        );

        $this->notifyEvento(
            $evento,
            '🆕 Nuevo evento disponible',
            $body,
            sprintf('/eventos/detalle/%s', $evento->getId())
        );
    }

    public function notifyInscripcionesAbiertas(Evento $evento): void
    {
        $body = sprintf(
            '¡Abierto plazo de inscripción para "%s"! 📝 Cierra: %s',
            $this->getEventoTitulo($evento),
            $this->formatFechaFinInscripcion($evento)
        );

        $this->notifyEvento(
            $evento,
            '📝 Inscripciones abiertas',
            $body,
            sprintf('/eventos/detalle/%s', $evento->getId())
        );
    }

    public function notifyInscripcionesCerradas(Evento $evento): void
    {
        $this->notifyEvento(
            $evento,
            '🔒 Inscripciones cerradas',
            sprintf('Se han cerrado las inscripciones de "%s".', $this->getEventoTitulo($evento)),
            sprintf('/eventos/detalle/%s', $evento->getId())
        );
    }

    public function notifyEventoCancelado(Evento $evento): void
    {
        $this->notifyEvento(
            $evento,
            '❌ Evento cancelado',
            sprintf('Se ha cancelado el evento "%s" previsto para %s.', $this->getEventoTitulo($evento), $this->formatFecha($evento)),
            sprintf('/eventos/detalle/%s', $evento->getId())
        );
    }

    private function notifyEvento(Evento $evento, string $title, string $body, string $url): void
    {
        // Obtener entidad directamente del evento, no del usuario logado
        $entidad = null;
        if (method_exists($evento, 'getEntidad')) {
            $entidad = $evento->getEntidad();
        }

        if (!$entidad || !method_exists($entidad, 'getId')) {
            return;
        }

        $entidadId = $entidad->getId();
        if ($entidadId === null) {
            return;
        }

        $subscriptions = $this->pushSubscriptionRepository->findByEntidadId($entidadId);
        if ($subscriptions === []) {
            return;
        }

        $this->pushNotificationService->sendToMany($subscriptions, $title, $body, $url);
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

    private function formatFecha(Evento $evento): string
    {
        if (method_exists($evento, 'getFechaEvento') && $evento->getFechaEvento()) {
            $fecha = $evento->getFechaEvento();
            return $fecha->format('d/m/Y');
        }

        return '';
    }

    private function formatHora(Evento $evento): string
    {
        if (method_exists($evento, 'getHoraInicio') && $evento->getHoraInicio()) {
            $hora = $evento->getHoraInicio();
            return sprintf(' ⏰ %s', $hora->format('H:i'));
        }

        return '';
    }

    private function formatLugar(Evento $evento): string
    {
        if (method_exists($evento, 'getLugar') && $evento->getLugar()) {
            $lugar = $evento->getLugar();
            return sprintf(' 📍 %s', $lugar);
        }

        return '';
    }

    private function formatFechaFinInscripcion(Evento $evento): string
    {
        if (method_exists($evento, 'getFechaFinInscripcion') && $evento->getFechaFinInscripcion()) {
            $fecha = $evento->getFechaFinInscripcion();
            return $fecha->format('d/m/Y H:i');
        }

        return 'próximamente';
    }
}
