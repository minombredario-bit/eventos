<?php
namespace App\EventListener;

use App\Entity\Evento;
use App\Repository\InscripcionRepository;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class EventoPreRemoveListener
{
    public function __construct(private readonly InscripcionRepository $inscripcionRepository)
    {
    }

    public function preRemove(object $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Evento) {
            return;
        }

        // Si hay inscripciones asociadas, bloquear el borrado por defecto
        $count = $this->inscripcionRepository->count(['evento' => $entity]);

        if ($count > 0) {
            throw new ConflictHttpException('No se puede eliminar el evento: existen inscripciones asociadas.');
        }
    }
}

