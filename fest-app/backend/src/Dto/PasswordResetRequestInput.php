<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class PasswordResetRequestInput
{
    #[Assert\NotBlank(message: 'El email o documento de identidad es requerido')]
    public string $identifier = '';

    #[Assert\NotBlank(message: 'El código de la entidad es requerido')]
    #[Assert\Length(
        min: 1,
        max: 50,
        minMessage: 'El código debe tener al menos 1 carácter',
        maxMessage: 'El código no debe exceder 50 caracteres'
    )]
    public string $codigoEntidad = '';
}

