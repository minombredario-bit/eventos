<?php

namespace App\Controller\Api;

use App\Entity\PushSubscription;
use App\Entity\Usuario;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api')]
class PushController
{
    public function __construct(
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
}
