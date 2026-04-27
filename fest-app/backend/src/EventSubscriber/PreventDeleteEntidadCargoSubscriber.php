<?php

namespace App\EventSubscriber;

use App\Entity\EntidadCargo;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Bundle\SecurityBundle\Security;

final class PreventDeleteEntidadCargoSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::preRemove,
        ];
    }

    public function preRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof EntidadCargo) {
            return;
        }

        // Allow superadmin to delete regardless of business protection
        if ($this->security->isGranted('ROLE_SUPERADMIN')) {
            return;
        }

        $entidad = $entity->getEntidad();
        $cargoMaster = $entity->getCargoMaster();

        if (!$entidad || !$cargoMaster) {
            return;
        }

        $tipoCodigo = $entidad->getTipoEntidad()?->getCodigo();

        // If entity is of type 'falla' and the cargoMaster code is one of the official ones,
        // prevent deletion through an explicit exception.
        $protected = [
            'DELEGADO_FESTEJOS', 'PRESIDENTE', 'PRESIDENTE_INFANTIL', 'VICESECRETARIO',
            'DELEGADO_PROTOCOLO', 'FALLERA_MAYOR_INFANTIL', 'VICEPRESIDENTE_1', 'TESORERO',
            'VICEPRESIDENTE_2', 'DELEGADO_CULTURA', 'FALLERA_MAYOR', 'DELEGADO_INFANTILES',
            'SECRETARIO', 'ABANDERADO_INFANTIL'
        ];

        if ($tipoCodigo === 'falla' && in_array($cargoMaster->getCodigo(), $protected, true)) {
            throw new BadRequestHttpException('No está permitido eliminar cargos oficiales para entidades de tipo "falla".');
        }
    }
}

