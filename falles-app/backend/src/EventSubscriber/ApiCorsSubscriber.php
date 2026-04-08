<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiCorsSubscriber implements EventSubscriberInterface
{
    /**
     * @param string[] $allowedOrigins
     */
    public function __construct(
        private readonly array $allowedOrigins,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 2048],
            KernelEvents::RESPONSE => ['onKernelResponse', -1024],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$this->isApiRequest($request) || !$request->isMethod(Request::METHOD_OPTIONS)) {
            return;
        }

        $response = new Response('', Response::HTTP_NO_CONTENT);
        $this->applyCorsHeaders($request, $response);

        $event->setResponse($response);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$this->isApiRequest($request)) {
            return;
        }

        if ($request->isMethod(Request::METHOD_OPTIONS)
            && $event->getResponse()->headers->has('Access-Control-Allow-Origin')) {
            return;
        }

        $this->applyCorsHeaders($request, $event->getResponse());
    }

    private function isApiRequest(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api');
    }

    private function applyCorsHeaders(Request $request, Response $response): void
    {
        $origin = $request->headers->get('Origin');

        if (!$this->isAllowedOrigin($origin)) {
            return;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->setVary(['Origin', 'Access-Control-Request-Method', 'Access-Control-Request-Headers'], false);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');

        $requestedHeaders = $request->headers->get('Access-Control-Request-Headers');
        $response->headers->set(
            'Access-Control-Allow-Headers',
            $requestedHeaders ?: 'Content-Type, Authorization, Accept, Origin, X-Requested-With'
        );
        $response->headers->set('Access-Control-Max-Age', '3600');
    }

    private function isAllowedOrigin(?string $origin): bool
    {
        if (null === $origin) {
            return false;
        }

        return in_array($origin, $this->allowedOrigins, true);
    }
}
