<?php

namespace App\EventSubscriber;

use App\Entity\Usuario;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class LopdAcceptanceSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Security $security) {}

    public static function getSubscribedEvents(): array
    {
        // high priority to run before other listeners
        return [KernelEvents::REQUEST => ['onKernelRequest', 8]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $user = $this->security->getUser();

        if (!$user instanceof Usuario) {
            // not authenticated or different user class -> nothing to do
            return;
        }

        // If the user already accepted, nothing to do
        if ($user->isAceptoLopd()) {
            return;
        }

        $route = $request->attributes->get('_route');
        $method = $request->getMethod();

        // Allow the specific PATCH endpoint to accept the LOPD
        if ($route === 'api_usuario_lopd' && strtoupper($method) === 'PATCH') {
            return;
        }

        // Otherwise deny access: user must accept LOPD before using the API
        throw new AccessDeniedHttpException('Debes aceptar las condiciones de uso (LOPD) para continuar.');
    }
}

