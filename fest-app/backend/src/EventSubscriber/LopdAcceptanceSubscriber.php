<?php

namespace App\EventSubscriber;

use App\Entity\Usuario;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class LopdAcceptanceSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // PRIORIDAD MENOR → se ejecuta después de password
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $method = strtoupper($request->getMethod());

        if (!str_starts_with($path, '/api/')) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof Usuario) {
            return;
        }

        // 🔴 CLAVE: si debe cambiar contraseña, NO aplicar LOPD
        if ($user->isDebeCambiarPassword()) {
            return;
        }

        if ($user->isAceptoLopd()) {
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

        // Obtener texto LOPD
        if ($method === 'GET' && $path === '/api/entidad/lopd') {
            return;
        }

        // Aceptar LOPD
        if ($method === 'PATCH' && preg_match('#^/api/usuarios/[^/]+/lopd$#', $path)) {
            return;
        }

        // Push (opcional pero necesario en muchos flujos)
        if ($method === 'POST' && in_array($path, [
                '/api/push/subscribe',
                '/api/push/unsubscribe'
            ], true)) {
            return;
        }

        // Bloqueo
        $event->setResponse(new JsonResponse([
            'error' => 'Debes aceptar las condiciones de uso (LOPD) para continuar.',
            'code' => 'LOPD_ACCEPTANCE_REQUIRED',
        ], 403));
    }
}
