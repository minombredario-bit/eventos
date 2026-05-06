<?php

namespace App\EventSubscriber;

use App\Entity\Usuario;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ForcePasswordChangeSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // PRIORIDAD ALTA → siempre antes que LOPD
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 200],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api/')) {
            return;
        }

        // Endpoints permitidos
        if (in_array($path, [
            '/api/login',
            '/api/logout',
            '/api/me',
            '/api/me/cambiar-password',
        ], true)) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof Usuario) {
            return;
        }

        if (!$user->isDebeCambiarPassword()) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'error' => 'Debes cambiar tu contraseña antes de continuar.',
            'code' => 'PASSWORD_CHANGE_REQUIRED',
        ], 403));
    }
}
