<?php

namespace App\EventSubscriber;

use App\Entity\Usuario;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ForcePasswordChangeSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // FIX: prioridad 0 (mayor que -5 de LopdAcceptanceSubscriber) para garantizar
            // que el check de contraseña se ejecuta siempre antes que el check de LOPD.
            // Si un usuario debe cambiar contraseña Y no ha aceptado LOPD, la contraseña tiene precedencia.
            KernelEvents::REQUEST => ['onKernelRequest', 0],
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

        if ($path === '/api/login' || $path === '/api/me/cambiar-password') {
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
