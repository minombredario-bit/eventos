<?php

namespace App\EventListener;

use App\Entity\ActividadEvento;
use App\Entity\Evento;
use App\Repository\InscripcionLineaRepository;
use App\Service\AuditLoggerService;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Doctrine\ORM\Event\PreRemoveEventArgs;

final class ActividadEventoPreRemoveListener
{
    public function __construct(
        private readonly InscripcionLineaRepository $inscripcionLineaRepository,
        private readonly AuditLoggerService $auditLogger
    ) {
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof ActividadEvento) {
            return;
        }

        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        $scheduledDeletions = $uow->getScheduledEntityDeletions();

        foreach ($scheduledDeletions as $scheduled) {
            if (
                $scheduled instanceof Evento &&
                $scheduled->getId() === $entity->getEvento()?->getId()
            ) {
                return;
            }
        }

        $count = $this->inscripcionLineaRepository->count([
            'actividad' => $entity,
        ]);

        if ($count > 0) {
            throw new ConflictHttpException(
                'No se puede eliminar la actividad: existen inscripciones.'
            );
        }

        $dump = [
            'id' => $entity->getId(),
            'nombre' => $entity->getNombre(),
            'descripcion' => $entity->getDescripcion(),
            'tipoActividad' => $entity->getTipoActividad()?->name ?? (string) $entity->getTipoActividad(),
            'franjaComida' => $entity->getFranjaComida()?->name ?? (string) $entity->getFranjaComida(),
            'eventoId' => $entity->getEvento()?->getId(),
            'precioBase' => $entity->getPrecioBase(),
        ];

        $this->auditLogger->log(
            'actividad_evento',
            (string) $entity->getId(),
            'delete',
            $dump,
            null,
            null
        );
    }
}
