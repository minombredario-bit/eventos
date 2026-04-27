<?php

namespace App\Service;

use App\Entity\PushSubscription;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

final class PushNotificationService
{
    public function __construct(
        private string $publicKey,
        private string $privateKey,
        private string $subject,
        private EntityManagerInterface $em
    ) {}

    public function send(PushSubscription $subscription, string $title, string $body, string $url = '/'): void
    {
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $this->subject,
                'publicKey' => $this->publicKey,
                'privateKey' => $this->privateKey,
            ],
        ]);

        $sub = Subscription::create([
            'endpoint' => $subscription->getEndpoint(),
            'publicKey' => $subscription->getP256dh(),
            'authToken' => $subscription->getAuth(),
        ]);

        $payload = json_encode([
            'notification' => [
                'title' => $title,
                'body' => $body,
                'icon' => '/icons/icon-192x192.png',
                'badge' => '/icons/icon-72x72.png',
                'data' => [
                    'url' => $url,
                ],
            ],
        ]);

        $webPush->queueNotification($sub, $payload);

        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                $statusCode = $report->getResponse()?->getStatusCode();
                if (in_array($statusCode, [404, 410])) {
                    $repo = $this->em->getRepository(PushSubscription::class);
                    $sub = $repo->findOneBy(['endpoint' => $report->getEndpoint()]);
                    if ($sub) {
                        $this->em->remove($sub);
                        $this->em->flush();
                    }
                }
            }
        }
    }
}
