<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\AdminUpdateUsuarioInput;
use App\Entity\RelacionUsuario;
use App\Entity\Usuario;
use App\Entity\UsuarioTemporadaCargo;
use App\Enum\MetodoPagoEnum;
use App\Enum\TipoRelacionEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class AdminUpdateUsuarioProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Usuario
    {
        if (!$data instanceof AdminUpdateUsuarioInput) {
            throw new \InvalidArgumentException('Payload inválido para actualización de usuario.');
        }

        $admin = $this->security->getUser();
        if (!$admin instanceof Usuario) {
            throw new AccessDeniedHttpException('Usuario no autenticado.');
        }

        if (
            !in_array('ROLE_ADMIN', $admin->getRoles(), true)
            && !in_array('ROLE_ADMIN_ENTIDAD', $admin->getRoles(), true)
            && !in_array('ROLE_SUPERADMIN', $admin->getRoles(), true)
        ) {
            throw new AccessDeniedHttpException('No tienes permisos para actualizar usuarios.');
        }

        $id = $uriVariables['id'] ?? null;
        if (!$id) {
            throw new BadRequestHttpException('ID de usuario no proporcionado.');
        }

        /** @var Usuario|null $usuario */
        $usuario = $this->entityManager->find(Usuario::class, $id);
        if (!$usuario instanceof Usuario) {
            throw new BadRequestHttpException('Usuario no encontrado.');
        }

        // Update scalar fields when provided
        if ($data->nombre !== null) {
            $usuario->setNombre(trim($data->nombre));
        }
        if ($data->apellidos !== null) {
            $usuario->setApellidos(trim($data->apellidos));
        }
        if ($data->telefono !== null) {
            $usuario->setTelefono($this->normalizeNullableString($data->telefono));
        }
        if ($data->activo !== null) {
            $usuario->setActivo((bool) $data->activo);
        }
        if ($data->motivoBajaCenso !== null) {
            $usuario->setMotivoBajaCenso($this->normalizeNullableString($data->motivoBajaCenso));
        }
        if ($data->fechaNacimiento !== null) {
            try {
                $usuario->setFechaNacimiento(new \DateTimeImmutable($data->fechaNacimiento));
            } catch (\Throwable) {
                throw new BadRequestHttpException('La fecha de nacimiento no es válida.');
            }
        }
        if ($data->antiguedad !== null) {
            $usuario->setAntiguedad((int) $data->antiguedad);
        }
        if ($data->antiguedadReal !== null) {
            $usuario->setAntiguedadReal((int) $data->antiguedadReal);
        }
        if ($data->formaPagoPreferida !== null) {
            $usuario->setFormaPagoPreferida($this->resolveMetodoPagoEnum($data->formaPagoPreferida));
        }
        if ($data->debeCambiarPassword !== null) {
            $usuario->setDebeCambiarPassword((bool) $data->debeCambiarPassword);
        }
        if ($data->roles !== null) {
            $usuario->setRoles($this->normalizeRoles($data->roles));
        }

        // Replace cargosTemporada if cargos provided
        if (is_array($data->cargos)) {
            // remove existing cargosTemporada
            foreach ($usuario->getCargosTemporada() as $utc) {
                $usuario->removeCargoTemporada($utc);
                $this->entityManager->remove($utc);
            }

            $cargosAsignados = $this->crearCargosTemporada($usuario, $data->cargos);
            foreach ($cargosAsignados as $cargoTemporada) {
                $this->entityManager->persist($cargoTemporada);
                $usuario->addCargoTemporada($cargoTemporada);
            }
        }

        // Replace relacionesOrigen if relacionUsuarios provided
        if (is_array($data->relacionUsuarios)) {
            // remove existing relacionesOrigen (we treat admin-managed relaciones as origen)
            foreach ($usuario->getRelacionesOrigen() as $rel) {
                $usuario->removeRelacionOrigen($rel);
                $this->entityManager->remove($rel);
            }

            foreach ($this->crearRelacionesUsuario($usuario, $data->relacionUsuarios) as $relacion) {
                $this->entityManager->persist($relacion);
                $usuario->addRelacionOrigen($relacion);
            }
        }

        $this->entityManager->persist($usuario);
        $this->entityManager->flush();

        return $usuario;
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** @param string[]|null $roles */
    private function normalizeRoles(?array $roles): array
    {
        if ($roles === null) {
            return ['ROLE_USER'];
        }

        $roles = array_values(array_unique(array_filter(array_map('strtoupper', $roles))));
        $allowed = ['ROLE_ADMIN', 'ROLE_USER'];
        $roles = array_values(array_intersect($roles, $allowed));

        if ($roles === []) {
            $roles = ['ROLE_USER'];
        }

        if (!in_array('ROLE_USER', $roles, true)) {
            $roles[] = 'ROLE_USER';
        }

        return $roles;
    }

    /** @param string[] $cargos */
    private function crearCargosTemporada(Usuario $usuario, array $cargos): array
    {
        $result = [];

        foreach ($cargos as $cargoRef) {
            $cargoId = $this->extractId($cargoRef);

            if ($cargoId === null) {
                continue;
            }

            $cargo = $this->entityManager->find(\App\Entity\Cargo::class, $cargoId);
            if ($cargo === null) {
                continue;
            }

            $cargoTemporada = new UsuarioTemporadaCargo();
            $cargoTemporada->setUsuario($usuario);
            $cargoTemporada->setCargo($cargo);

            $result[] = $cargoTemporada;
        }

        return $result;
    }

    /** @param array<int, array{usuario?: string, tipoRelacion?: string}> $relacionUsuarios */
    private function crearRelacionesUsuario(Usuario $usuario, array $relacionUsuarios): array
    {
        $result = [];
        $seen = [];

        foreach ($relacionUsuarios as $item) {
            if (!is_array($item)) {
                continue;
            }

            $usuarioRef = $item['usuario'] ?? $item['usuario_id'] ?? null;
            $tipoRelacionRaw = $item['tipoRelacion'] ?? null;

            if (!is_string($usuarioRef) || !is_string($tipoRelacionRaw)) {
                continue;
            }

            $usuarioDestinoId = $this->extractId($usuarioRef);

            if ($usuarioDestinoId === null) {
                continue;
            }

            $dedupeKey = $usuarioDestinoId . '|' . strtolower(trim($tipoRelacionRaw));
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $usuarioDestino = $this->entityManager->find(Usuario::class, $usuarioDestinoId);
            if (!$usuarioDestino instanceof Usuario) {
                continue;
            }

            if ((string) $usuarioDestino->getId() === (string) $usuario->getId()) {
                continue;
            }

            $relacion = new RelacionUsuario();
            $relacion->setUsuarioOrigen($usuario);
            $relacion->setUsuarioDestino($usuarioDestino);
            $tipoRelacion = $this->resolveTipoRelacionEnum($tipoRelacionRaw);
            $relacion->setTipoRelacion($tipoRelacion);

            $result[] = $relacion;
        }

        return $result;
    }

    private function extractId(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (str_contains($value, '/')) {
            $parts = explode('/', trim($value, '/'));
            return end($parts) ?: null;
        }

        return $value;
    }

    private function resolveTipoRelacionEnum(string $value): TipoRelacionEnum
    {
        $v = strtolower(trim($value));

        // Try to match directly against enum backed values
        foreach (TipoRelacionEnum::cases() as $case) {
            if ($case->value === $v) {
                return $case;
            }
        }

        $map = [
            'conyuge' => TipoRelacionEnum::CONYUGE,
            'cónyuge' => TipoRelacionEnum::CONYUGE,
            'pareja' => TipoRelacionEnum::PAREJA,
            'padre' => TipoRelacionEnum::PADRE,
            'madre' => TipoRelacionEnum::MADRE,
            'hijo' => TipoRelacionEnum::HIJO,
            'hija' => TipoRelacionEnum::HIJA,
            'sobrino' => TipoRelacionEnum::SOBRINO,
            'sobrina' => TipoRelacionEnum::SOBRINA,
            'abuelo' => TipoRelacionEnum::ABUELO,
            'abuela' => TipoRelacionEnum::ABUELA,
        ];

        if (isset($map[$v])) {
            return $map[$v];
        }

        throw new BadRequestHttpException('tipoRelacion no válido.');
    }

    private function resolveMetodoPagoEnum(string $value): MetodoPagoEnum
    {
        return match (strtolower(trim($value))) {
            'efectivo' => MetodoPagoEnum::EFECTIVO,
            'tarjeta' => MetodoPagoEnum::TARJETA,
            'transferencia' => MetodoPagoEnum::TRANSFERENCIA,
            'bizum' => MetodoPagoEnum::BIZUM,
            'tpv' => MetodoPagoEnum::TPV,
            'online' => MetodoPagoEnum::ONLINE,
            'manual' => MetodoPagoEnum::MANUAL,
            default => throw new BadRequestHttpException('formaPagoPreferida no válida.'),
        };
    }
}

