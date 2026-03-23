<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener(event: LoginSuccessEvent::class, method: '__invoke', priority: 100)]
class JWTAuthenticationSuccessListener
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {}

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $token = $this->jwtManager->create($user);
        
        $event->getRequest()->attributes->set('jwt_token', $token);
    }
}
