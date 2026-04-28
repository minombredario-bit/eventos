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
        // Prioridad -5: se ejecuta después de ForcePasswordChangeSubscriber (prioridad 0)
        return [KernelEvents::REQUEST => ['onKernelRequest', -5]];
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

        $path = $request->getPathInfo();
        $method = strtoupper($request->getMethod());

        // Only enforce for API calls
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        // Allow login and password-change endpoints (they must remain accessible)
        if ($path === '/api/login' || $path === '/api/me/cambiar-password') {
            return;
        }

        // Allow the LOPD text endpoint
        if ($method === 'GET' && $path === '/api/entidad/lopd') {
            return;
        }

        // Allow the specific PATCH endpoint to accept the LOPD: /api/usuarios/{id}/lopd
        if ($method === 'PATCH' && preg_match('#^/api/usuarios/[^/]+/lopd$#', $path)) {
            return;
        }

        // FIX: permitir el endpoint de suscripción push aunque no se haya aceptado LOPD,
        // ya que puede necesitarse justo después del login antes de completar el flujo LOPD
        if ($method === 'POST' && $path === '/api/push/subscribe') {
            return;
        }

        // Otherwise deny access: user must accept LOPD before using the API
        throw new AccessDeniedHttpException('Debes aceptar las condiciones de uso (LOPD) para continuar.');
    }
}
