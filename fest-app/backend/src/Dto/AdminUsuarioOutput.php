<?php

namespace App\Dto;

use App\Entity\Usuario;
use Symfony\Component\Serializer\Annotation\Groups;

final class AdminUsuarioOutput
{
    #[Groups(['admin_usuario_output'])]
    public Usuario $usuario;

    #[Groups(['admin_usuario_output'])]
    public ?string $passwordPlano;

    public function __construct(Usuario $usuario, ?string $passwordPlano = null)
    {
        $this->usuario = $usuario;
        $this->passwordPlano = $passwordPlano;
    }
}
