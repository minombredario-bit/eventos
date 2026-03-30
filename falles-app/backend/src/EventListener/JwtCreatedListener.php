<?php

namespace App\EventListener;

use App\Entity\Usuario;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;

class JwtCreatedListener
{
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof Usuario) {
            return;
        }

        $payload = $event->getData();
        
        // Agregar datos del usuario al payload del token
        $payload['id'] = (string) $user->getId();
        $payload['nombre'] = $user->getNombre();
        $payload['apellidos'] = $user->getApellidos();
        
        $fechaNacimiento = $user->getFechaNacimiento();
        $payload['fechaNacimiento'] = $fechaNacimiento ? $fechaNacimiento->format('Y-m-d') : null;
        
        $event->setData($payload);
    }
}
