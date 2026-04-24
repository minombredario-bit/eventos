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
            'fechaNacimiento' => $user->getFechaNacimiento()?->format('d-m-Y'),
            'telefono' => $user->getTelefono(),
            'formaPagoPreferida' => $user->getFormaPagoPreferida()?->value,
            'debeCambiarPassword' => $user->isDebeCambiarPassword(),
            'roles' => $user->getRoles(),
            'nombreEntidad' => $user->getEntidad()->getNombre(),
            'tipoEntidad' => mb_strtolower($user->getEntidad()->getTipoEntidad()?->getNombre() ?? ''),
            'aceptoLopd' => $user->isAceptoLopd(),
            'aceptoLopdAt' => $user->getAceptoLopdAt()?->format(DATE_ATOM),
        ];

        $event->setData($data);
    }
}
