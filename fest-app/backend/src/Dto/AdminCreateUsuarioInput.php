<?php

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminCreateUsuarioInput
{
    #[Groups(['admin_usuario_create'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    public ?string $nombre = null;

    #[Groups(['admin_usuario_create'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 150)]
    public ?string $apellidos = null;

    #[Groups(['admin_usuario_create'])]
    public ?string $direccion = null;

    #[Groups(['admin_usuario_create'])]
    #[Assert\Email]
    public ?string $email = null;

    #[Groups(['admin_usuario_create'])]
    public ?string $telefono = null;

    #[Groups(['admin_usuario_create'])]
    #[Assert\Length(max: 15)]
    public ?string $documentoIdentidad = null;

    #[Groups(['admin_usuario_create'])]
    #[Assert\NotNull]
    #[Assert\Count(min: 1)]
    public array $roles = ['ROLE_USER'];

    #[Groups(['admin_usuario_create'])]
    public bool $activo = true;

    #[Groups(['admin_usuario_create'])]
    public bool $debeCambiarPassword = true;

    #[Groups(['admin_usuario_create'])]
    public ?string $formaPagoPreferida = 'efectivo';

    #[Groups(['admin_usuario_create'])]
    #[Assert\NotBlank]
    #[Assert\Date]
    public ?string $fechaNacimiento = null;

    #[Groups(['admin_usuario_create'])]
    public ?int $antiguedad = null;

    #[Groups(['admin_usuario_create'])]
    public ?int $antiguedadReal = null;

    #[Groups(['admin_usuario_create'])]
    public ?string $motivoBajaCenso = null;

    /**
     * Cada elemento:
     * [
     *   'usuario' => '/api/usuarios/uuid' | 'uuid',
     *   'tipoRelacion' => 'familiar'
     * ]
     */
    #[Groups(['admin_usuario_create'])]
    public array $relacionUsuarios = [];

    #[Groups(['admin_usuario_create'])]
    public array $cargos = [];

    #[Groups(['admin_usuario_create'])]
    public ?string $temporada = null;
}
