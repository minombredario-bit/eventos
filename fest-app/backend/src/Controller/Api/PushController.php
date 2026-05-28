<?php

namespace App\Controller\Api;

use App\Entity\PushSubscription;
use App\Entity\Usuario;
use App\Repository\PushSubscriptionRepository;
use App\Service\PushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api')]
class PushController
{
    public function __construct(
        private PushSubscriptionRepository $pushSubscriptionRepository,
        private EntityManagerInterface $em,
        private PushNotificationService $pushNotificationService,
    ) {}

    #[Route('/push/subscribe', methods: ['POST'])]
    public function subscribe(Request $request, EntityManagerInterface $em, #[CurrentUser] ?Usuario $user): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse(['error' => 'No autenticado'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        $endpoint = $data['endpoint'] ?? null;
        $p256dh   = $data['keys']['p256dh'] ?? null;
        $auth     = $data['keys']['auth'] ?? null;

        if (!$endpoint || !$p256dh || !$auth) {
            return new JsonResponse(['error' => 'Invalid subscription'], 400);
        }

        $repo = $em->getRepository(PushSubscription::class);
        $subscription = $repo->findOneBy(['endpoint' => $endpoint]) ?? new PushSubscription();

        $subscription->setEndpoint($endpoint);
        $subscription->setP256dh($p256dh);
        $subscription->setAuth($auth);
        $subscription->setUpdatedAt(new \DateTimeImmutable());

        // FIX: vincular la suscripción al usuario y su entidad (ambos vienen del usuario autenticado)
        $subscription->setUsuarioId((string) $user->getId());
        $subscription->setEntidadId((string) $user->getEntidad()->getId());

        if (!$subscription->getId()) {
            $subscription->setCreatedAt(new \DateTimeImmutable());
        }

        $em->persist($subscription);
        $em->flush();

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/push/unsubscribe', methods: ['POST'])]
    public function unsubscribe(
        Request $request,
        PushSubscriptionRepository $pushSubscriptionRepository,
        EntityManagerInterface $em,
        #[CurrentUser] ?Usuario $user
    ): JsonResponse {
        if (null === $user) {
            return new JsonResponse(['error' => 'No autenticado'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $endpoint = $data['endpoint'] ?? null;

        if (!is_string($endpoint) || $endpoint === '') {
            return new JsonResponse(['error' => 'Invalid endpoint'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $subscription = $pushSubscriptionRepository->findOneByEndpoint($endpoint);

        if ($subscription !== null && $subscription->getUsuarioId() === (string) $user->getId()) {
            $em->remove($subscription);
            $em->flush();
        }

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/me/notificaciones-push/status', methods: ['GET'])]
    public function getNotificacionesStatus(#[CurrentUser] ?Usuario $user): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse(['error' => 'No autenticado'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $subscriptions = $this->pushSubscriptionRepository->findByUsuarioId((string) $user->getId());
        $hasSubscription = count($subscriptions) > 0;

        return new JsonResponse(['hasSubscription' => $hasSubscription]);
    }

    #[Route('/me/notificaciones-push/subscribe', methods: ['POST'])]
    public function subscribeNotificaciones(Request $request, #[CurrentUser] ?Usuario $user): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse(['error' => 'No autenticado'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $endpoint = $data['endpoint'] ?? null;
        $p256dh = $data['keys']['p256dh'] ?? null;
        $auth = $data['keys']['auth'] ?? null;

        if (!$endpoint || !$p256dh || !$auth) {
            return new JsonResponse(['error' => 'Invalid subscription data'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Buscar si ya existe una suscripción con este endpoint
        $subscription = $this->pushSubscriptionRepository->findOneByEndpoint($endpoint);

        if ($subscription === null) {
            $subscription = new PushSubscription();
            $subscription->setCreatedAt(new \DateTimeImmutable());
        }

        $subscription->setEndpoint($endpoint);
        $subscription->setP256dh($p256dh);
        $subscription->setAuth($auth);
        $subscription->setUpdatedAt(new \DateTimeImmutable());
        $subscription->setUsuarioId((string) $user->getId());
        $subscription->setEntidadId((string) $user->getEntidad()->getId());

        $this->em->persist($subscription);
        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/me/notificaciones-push/unsubscribe', methods: ['POST'])]
    public function unsubscribeNotificaciones(#[CurrentUser] ?Usuario $user): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse(['error' => 'No autenticado'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $subscriptions = $this->pushSubscriptionRepository->findByUsuarioId((string) $user->getId());

        foreach ($subscriptions as $subscription) {
            $this->em->remove($subscription);
        }

        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/me/notificaciones-push/test', methods: ['POST'])]
    public function testNotification(#[CurrentUser] ?Usuario $user): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse(['error' => 'No autenticado'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $subscriptions = $this->pushSubscriptionRepository->findByUsuarioId((string) $user->getId());

        if (empty($subscriptions)) {
            return new JsonResponse(['error' => 'No active subscriptions'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Enviar notificación de prueba
        try {
            $this->pushNotificationService->sendToMany(
                $subscriptions,
                '🔔 Notificación de prueba',
                'Esto es una notificación de prueba de ' . ($user->getEntidad()?->getNombre() ?? 'la aplicación'),
                '/'
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Test notification sent',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to send test notification',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
