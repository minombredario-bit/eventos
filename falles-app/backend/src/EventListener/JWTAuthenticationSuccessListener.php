<?php

namespace App\EventListener;

use App\Entity\Usuario;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;

class JWTAuthenticationSuccessListener
{
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof Usuario) {
            return;
        }

        $data = $event->getData();

        $data['user'] = [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'nombre' => $user->getNombre(),
            'apellidos' => $user->getApellidos(),
            'fechaNacimiento' => $user->getFechaNacimiento()?->format('Y-m-d'),
            'roles' => $user->getRoles(),
        ];

        $event->setData($data);
    }
}
