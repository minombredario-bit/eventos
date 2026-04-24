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
        $payload['roles'] = $user->getRoles();

        $fechaNacimiento = $user->getFechaNacimiento();
        $payload['fechaNacimiento'] = $fechaNacimiento ? $fechaNacimiento->format('Y-m-d') : null;
        // Include LOPD acceptance in the token so frontend can restore session state
        $payload['aceptoLopd'] = $user->isAceptoLopd();
        $payload['aceptoLopdAt'] = $user->getAceptoLopdAt()?->format(DATE_ATOM);

        $event->setData($payload);
    }
}
