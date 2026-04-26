<?php

namespace App\EventSubscriber;

use App\Entity\Entidad;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('doctrine.event_subscriber')]
final class ProtectTipoEntidadSubscriber implements EventSubscriber
{
    public function __construct(private readonly Security $security)
    {
    }

    /** @return string[] */
    public function getSubscribedEvents(): array
    {
        return ['preUpdate'];
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Entidad) {
            return;
        }

        // If tipoEntidad is not part of the change set, nothing to do
        if (!$args->hasChangedField('tipoEntidad')) {
            return;
        }

        // Superadmin and ROLE_ADMIN are allowed to change it
        if ($this->security->isGranted('ROLE_SUPERADMIN') || $this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        // ROLE_ADMIN_ENTIDAD (and other roles) are forbidden to change tipoEntidad
        if ($this->security->isGranted('ROLE_ADMIN_ENTIDAD')) {
            throw new AccessDeniedException('You are not allowed to change tipoEntidad');
        }
    }
}

