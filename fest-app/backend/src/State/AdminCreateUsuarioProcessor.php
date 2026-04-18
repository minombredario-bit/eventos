<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\AdminCreateUsuarioInput;
use App\Entity\Cargo;
use App\Entity\EntidadCargo;
use App\Entity\RelacionUsuario;
use App\Entity\Usuario;
use App\Entity\UsuarioTemporadaCargo;
use App\Enum\CensadoViaEnum;
use App\Enum\MetodoPagoEnum;
use App\Enum\TipoPersonaEnum;
use App\Enum\TipoRelacionEconomicaEnum;
use App\Enum\TipoRelacionEnum;
use App\Service\EmailQueueService;
use App\Repository\CargoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminCreateUsuarioProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EmailQueueService $emailQueueService,
        private readonly string $appUri,
        private readonly CargoRepository $cargoRepository,
        private readonly \App\Repository\TemporadaEntidadRepository $temporadaEntidadRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Usuario
    {
        if (!$data instanceof AdminCreateUsuarioInput) {
            throw new \InvalidArgumentException('Payload inválido para creación de usuario.');
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
            throw new AccessDeniedHttpException('No tienes permisos para crear usuarios.');
        }

        $entidad = $admin->getEntidad();
        if ($entidad === null) {
            throw new BadRequestHttpException('El administrador no tiene entidad asignada.');
        }

        $usuario = new Usuario();
        $usuario
            ->setEntidad($entidad)
            ->setNombre(trim($data->nombre))
            ->setApellidos(trim($data->apellidos))
            ->setTelefono($this->normalizeNullableString($data->telefono))
            ->setActivo((bool) $data->activo)
            ->setDebeCambiarPassword((bool) $data->debeCambiarPassword)
            ->setCensadoVia(CensadoViaEnum::MANUAL)
            ->setFormaPagoPreferida($this->resolveMetodoPagoEnum($data->formaPagoPreferida))
            ->setFechaAltaCenso(new \DateTimeImmutable())
            ->setFechaNacimiento($this->parseFechaNacimiento($data->fechaNacimiento))
            ->setMotivoBajaCenso($data->activo ? null : $this->normalizeNullableString($data->motivoBajaCenso));

        $usuario->setRoles($this->normalizeRoles($data->roles));
        $usuario->setTipoPersona($this->calcularTipoPersona($usuario->getFechaNacimiento()));

        $antiguedadReal = $data->antiguedadReal ?? $this->calcularAntiguedadReal($usuario);
        $usuario->setAntiguedadReal(max(0, (int) $antiguedadReal));

        $cargosAsignados = $this->crearCargosTemporada($usuario, $data->cargos, $data->temporada ?? null);
        $antiguedad = $data->antiguedad ?? $this->calcularAntiguedad($usuario->getAntiguedadReal(), count($cargosAsignados));
        $usuario->setAntiguedad(max(0, (int) $antiguedad));

        $passwordPlano = $this->generateRandomPassword();
        $usuario->setPassword(
            $this->passwordHasher->hashPassword($usuario, $passwordPlano)
        );

        // Normalizar email sólo si se proporciona. Permitimos null.
        $emailNormalized = $this->normalizeNullableString($data->email);
        $usuario->setEmail($emailNormalized !== null ? mb_strtolower($emailNormalized) : null);

        $this->entityManager->persist($usuario);

        foreach ($cargosAsignados as $cargoTemporada) {
            $this->entityManager->persist($cargoTemporada);
            $usuario->addCargoTemporada($cargoTemporada);
        }

        foreach ($this->crearRelacionesUsuario($usuario, $data->relacionUsuarios) as $relacion) {
            $this->entityManager->persist($relacion);
            $usuario->addRelacionOrigen($relacion);
        }

        $this->emailQueueService->enqueueUserWelcome($usuario, $passwordPlano, $this->appUri);

        $this->entityManager->flush();

        return $usuario;
    }

    private function parseFechaNacimiento(?string $fechaNacimiento): \DateTimeImmutable
    {
        if ($fechaNacimiento === null || trim($fechaNacimiento) === '') {
            throw new BadRequestHttpException('La fecha de nacimiento es obligatoria.');
        }

        try {
            return new \DateTimeImmutable($fechaNacimiento);
        } catch (\Throwable) {
            throw new BadRequestHttpException('La fecha de nacimiento no es válida.');
        }
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param string[] $roles
     * @return string[]
     */
    private function normalizeRoles(array $roles): array
    {
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

    private function calcularTipoPersona(\DateTimeImmutable $fechaNacimiento): TipoPersonaEnum
    {
        $hoy = new \DateTimeImmutable('today');
        $edad = $fechaNacimiento->diff($hoy)->y;

        return match (true) {
            $edad <= 13 => TipoPersonaEnum::INFANTIL,
            $edad <= 18 => TipoPersonaEnum::CADETE,
            default => TipoPersonaEnum::ADULTO,
        };
    }

    private function calcularAntiguedadReal(Usuario $usuario): int
    {
        $fechaAlta = $usuario->getFechaAltaCenso();

        if (!$fechaAlta instanceof \DateTimeImmutable) {
            return 0;
        }

        $hoy = new \DateTimeImmutable('today');

        return max(0, $fechaAlta->diff($hoy)->y);
    }

    private function calcularAntiguedad(int $antiguedadReal, int $numeroCargos): int
    {
        return $numeroCargos > 0 ? $antiguedadReal + $numeroCargos : $antiguedadReal;
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

    /**
     * @param array<int, string|array<string, mixed>> $cargos
     * @return UsuarioTemporadaCargo[]
     */
    private function crearCargosTemporada(Usuario $usuario, array $cargos, ?string $temporadaInput = null): array
    {
        $result = [];
        $seenCargoIds = [];
        // Determine temporada to use: prefer DTO->temporada if provided, else use current season format (YYYY/YY+1)
        $temporada = null;

        if (!empty($temporadaInput)) {
            $tid = $this->extractId($temporadaInput);
            if ($tid !== null) {
                // try id first
                $temporada = $this->entityManager->find(\App\Entity\TemporadaEntidad::class, $tid)
                    ?? $this->temporadaEntidadRepository->findOneByEntidadAndCodigo($usuario->getEntidad(), $tid);

                // if not found and tid looks like a year (4 digits), try season code
                if ($temporada === null && preg_match('/^\d{4}$/', $tid)) {
                    $seasonCode = $this->seasonCodeFromYear((int) $tid);
                    $temporada = $this->temporadaEntidadRepository->findOneByEntidadAndCodigo($usuario->getEntidad(), $seasonCode);
                }
            }
        }

        if ($temporada === null) {
            $currentYear = (int) (new \DateTimeImmutable())->format('Y');
            $seasonCode = $this->seasonCodeFromYear($currentYear);
            $temporada = $this->temporadaEntidadRepository->findOneByEntidadAndCodigo($usuario->getEntidad(), $seasonCode);
            if ($temporada === null) {
                // create temporada for current season code
                $temporada = new \App\Entity\TemporadaEntidad();
                $temporada->setEntidad($usuario->getEntidad());
                $temporada->setCodigo($seasonCode);
                $temporada->setNombre('Temporada ' . $seasonCode);
                $this->entityManager->persist($temporada);
            }
        }

        foreach ($cargos as $cargoRef) {
            $cargo = $this->resolveCargoOperativo($usuario, $cargoRef);
            if ($cargo === null) {
                continue;
            }

            $cargoId = $cargo->getId();
            if ($cargoId === null || isset($seenCargoIds[$cargoId])) {
                continue;
            }
            $seenCargoIds[$cargoId] = true;

            $cargoTemporada = new UsuarioTemporadaCargo();
            $cargoTemporada->setUsuario($usuario);
            $cargoTemporada->setCargo($cargo);
            $cargoTemporada->setTemporada($temporada);

            $result[] = $cargoTemporada;
        }

        return $result;
    }

    /**
     * @param array<int, array{usuario?: string, usuario_id?: string, tipoRelacion?: string}> $relacionUsuarios
     * @return RelacionUsuario[]
     */
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

    private function resolveCargoOperativo(Usuario $usuario, string|array $cargoRef): ?Cargo
    {
        $cargoId = $this->extractId($cargoRef);

        if ($cargoId === null) {
            return null;
        }

        $cargo = $this->cargoRepository->findOneByIdAndEntidad($usuario->getEntidad(), $cargoId);
        if ($cargo instanceof Cargo) {
            return $cargo;
        }

        $entidadCargo = $this->entityManager->find(EntidadCargo::class, $cargoId);
        if (!$entidadCargo instanceof EntidadCargo) {
            return null;
        }

        if ($entidadCargo->getEntidad()?->getId() !== $usuario->getEntidad()->getId()) {
            return null;
        }

        return $entidadCargo->getCargo() instanceof Cargo ? $entidadCargo->getCargo() : null;
    }

    private function extractId(string|array $value): ?string
    {
        if (is_array($value)) {
            $candidates = [
                $value['id'] ?? null,
                $value['cargo_id'] ?? null,
                $value['cargoId'] ?? null,
                $value['registroId'] ?? null,
                $value['entidadCargoId'] ?? null,
                $value['cargo']['id'] ?? null,
                $value['cargo']['@id'] ?? null,
                $value['@id'] ?? null,
            ];

            foreach ($candidates as $candidate) {
                if (!is_string($candidate)) {
                    continue;
                }

                $resolved = $this->extractId($candidate);
                if ($resolved !== null) {
                    return $resolved;
                }
            }

            return null;
        }

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

    private function seasonCodeFromYear(int $year): string
    {
        $next = $year + 1;
        $nextShort = substr((string) $next, -2);

        return sprintf('%d/%s', $year, $nextShort);
    }

    private function generateRandomPassword(int $length = 12): string
    {
        $raw = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return substr($raw, 0, $length) . 'A1!';
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

        // Common synonyms mapping to enum cases
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
}
