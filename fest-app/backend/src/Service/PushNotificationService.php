<?php

namespace App\Service;

use App\Entity\PushSubscription;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

final class PushNotificationService
{
    public function __construct(
        private string $publicKey,
        private string $privateKey,
        private string $subject,
        private readonly EntityManagerInterface $em,
        private readonly PushSubscriptionRepository $pushSubscriptionRepository,
    ) {}

    public function send(PushSubscription $subscription, string $title, string $body, string $url = '/'): void
    {
        $webPush = new WebPush([
            'VAPID' => [
                'subject'    => $this->subject,
                'publicKey'  => $this->publicKey,
                'privateKey' => $this->privateKey,
            ],
        ]);

        $sub = Subscription::create([
            'endpoint'  => $subscription->getEndpoint(),
            'publicKey' => $subscription->getP256dh(),
            'authToken' => $subscription->getAuth(),
        ]);

        $payload = json_encode([
            'notification' => [
                'title'  => $title,
                'body'   => $body,
                'icon'   => '/icons/icon-192x192.png',
                'badge'  => '/icons/icon-72x72.png',
                'data'   => ['url' => $url],
            ],
        ]);

        $webPush->queueNotification($sub, $payload);

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            $statusCode = $report->getResponse()?->getStatusCode();
            if (in_array($statusCode, [404, 410], true)) {
                $expired = $this->pushSubscriptionRepository->findOneBy([
                    'endpoint' => $report->getEndpoint(),
                ]);
                if ($expired !== null) {
                    $this->em->remove($expired);
                    $this->em->flush();
                }
            }
        }
    }

    /**
     * Envía a múltiples suscripciones usando la cola de WebPush.
     * Más eficiente que llamar a send() en bucle cuando hay muchos destinatarios.
     *
     * @param PushSubscription[] $subscriptions
     */
    public function sendToMany(array $subscriptions, string $title, string $body, string $url = '/'): void
    {
        if ($subscriptions === []) {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject'    => $this->subject,
                'publicKey'  => $this->publicKey,
                'privateKey' => $this->privateKey,
            ],
        ]);

        $payload = json_encode([
            'notification' => [
                'title'  => $title,
                'body'   => $body,
                'icon'   => '/icons/icon-192x192.png',
                'badge'  => '/icons/icon-72x72.png',
                'data'   => ['url' => $url],
            ],
        ]);

        foreach ($subscriptions as $subscription) {
            $sub = Subscription::create([
                'endpoint'  => $subscription->getEndpoint(),
                'publicKey' => $subscription->getP256dh(),
                'authToken' => $subscription->getAuth(),
            ]);
            $webPush->queueNotification($sub, $payload);
        }

        $toRemove = [];

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            $statusCode = $report->getResponse()?->getStatusCode();
            if (in_array($statusCode, [404, 410], true)) {
                $toRemove[] = $report->getEndpoint();
            }
        }

        if ($toRemove !== []) {
            foreach ($toRemove as $endpoint) {
                $expired = $this->pushSubscriptionRepository->findOneBy(['endpoint' => $endpoint]);
                if ($expired !== null) {
                    $this->em->remove($expired);
                }
            }
            $this->em->flush();
        }
    }
}
