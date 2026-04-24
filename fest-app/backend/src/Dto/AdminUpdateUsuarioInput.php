<?php

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\Groups;

final class AdminUpdateUsuarioInput
{
    #[Groups(['admin_usuario_update'])]
    public ?string $nombre = null;

    #[Groups(['admin_usuario_update'])]
    public ?string $apellidos = null;

    #[Groups(['admin_usuario_update'])]
    public ?string $email = null;

    #[Groups(['admin_usuario_update'])]
    public ?string $telefono = null;

    #[Groups(['admin_usuario_update'])]
    public ?array $roles = null;

    #[Groups(['admin_usuario_update'])]
    public ?bool $activo = null;

    #[Groups(['admin_usuario_update'])]
    public ?bool $debeCambiarPassword = null;

    #[Groups(['admin_usuario_update'])]
    public ?string $formaPagoPreferida = null;

    #[Groups(['admin_usuario_update'])]
    public ?string $fechaNacimiento = null;

    #[Groups(['admin_usuario_update'])]
    public ?int $antiguedad = null;

    #[Groups(['admin_usuario_update'])]
    public ?int $antiguedadReal = null;

    #[Groups(['admin_usuario_update'])]
    public ?string $motivoBajaCenso = null;

    #[Groups(['admin_usuario_update'])]
    public ?bool $aceptoLopd = null;

    /**
     * @var array<int, array{usuario?: string, tipoRelacion?: string}>|null
     */
    #[Groups(['admin_usuario_update'])]
    public ?array $relacionUsuarios = null;

    #[Groups(['admin_usuario_update'])]
    public ?array $cargos = null;

    #[Groups(['admin_usuario_update'])]
    public ?string $temporada = null;
}

