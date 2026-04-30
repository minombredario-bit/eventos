<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\AdminCreateUsuarioInput;
use App\Dto\AdminUpdateUsuarioInput;
use App\Dto\AdminUsuarioOutput;
use App\Entity\EntidadCargo;
use App\Entity\RelacionUsuario;
use App\Entity\TemporadaEntidad;
use App\Entity\Usuario;
use App\Entity\UsuarioTemporadaCargo;
use App\Enum\CensadoViaEnum;
use App\Enum\MetodoPagoEnum;
use App\Enum\TipoRelacionEnum;
use App\Repository\CargoRepository;
use App\Repository\TemporadaEntidadRepository;
use App\Service\EmailQueueService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminUsuarioProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EmailQueueService $emailQueueService,
        private readonly string $appUri,
        private readonly TemporadaEntidadRepository $temporadaEntidadRepository,
        private readonly CargoRepository $cargoRepository,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminUsuarioOutput
    {
        $admin = $this->getAdmin();

        return match (true) {
            $data instanceof AdminCreateUsuarioInput => $this->handleCreate($data, $admin),
            $data instanceof AdminUpdateUsuarioInput => $this->handleUpdate($data, $admin, $uriVariables),
            default => throw new \InvalidArgumentException('Payload no soportado'),
        };
    }

    /* ================= CREATE ================= */

    private function handleCreate(AdminCreateUsuarioInput $data, Usuario $admin): AdminUsuarioOutput
    {
        $usuario = new Usuario();

        $usuario
            ->setEntidad($admin->getEntidad())
            ->setCensadoVia(CensadoViaEnum::MANUAL)
            ->setFechaAltaCenso(new \DateTimeImmutable());

        $this->applyCommon($usuario, $data, true);

        $passwordPlano = $this->generateRandomPassword();
        $usuario->setPassword($this->passwordHasher->hashPassword($usuario, $passwordPlano));
        $usuario->setDebeCambiarPassword(true);

        foreach ($this->crearCargosTemporada($usuario, $data->cargos ?? []) as $utc) {
            $usuario->addCargoTemporada($utc);
            $this->entityManager->persist($utc);
        }

        foreach ($this->crearRelacionesUsuario($usuario, $data->relacionUsuarios ?? []) as $rel) {
            $usuario->addRelacionOrigen($rel);
            $this->entityManager->persist($rel);
        }

        $this->entityManager->persist($usuario);
        $this->entityManager->flush();

        if ($usuario->getEmail()) {
            $this->emailQueueService->enqueueUserWelcome($usuario, $passwordPlano, $this->appUri);
            return new AdminUsuarioOutput($usuario, null);
        }

        return new AdminUsuarioOutput($usuario, $passwordPlano);
    }

    /* ================= UPDATE ================= */

    private function handleUpdate(AdminUpdateUsuarioInput $data, Usuario $admin, array $uriVariables): AdminUsuarioOutput
    {
        $id = $uriVariables['id'] ?? null;

        if (!$id) {
            throw new BadRequestHttpException('ID de usuario no proporcionado.');
        }

        $usuario = $this->entityManager->find(Usuario::class, $id);

        if (!$usuario instanceof Usuario) {
            throw new BadRequestHttpException('Usuario no encontrado');
        }

        $this->assertCanEdit($admin, $usuario);

        $emailAnterior = $this->normalizeNullable($usuario->getEmail());
        $passwordPlano = null;
        $welcomeYaEncolado = false;

        $this->applyCommon($usuario, $data, false);

        $emailNuevo = $this->normalizeNullable($usuario->getEmail());

        // EMAIL
        if ($data->email !== null && $emailNuevo !== null && $emailAnterior !== $emailNuevo) {
            if ($emailAnterior !== null) {
                $this->emailQueueService->enqueueUserEmailChanged(
                    $usuario,
                    $emailAnterior,
                    $emailNuevo,
                    $this->appUri
                );
            } else {
                $passwordPlano = $this->generateRandomPassword();

                $usuario->setPassword(
                    $this->passwordHasher->hashPassword($usuario, $passwordPlano)
                );

                $usuario->setDebeCambiarPassword(true);

                $this->emailQueueService->enqueueUserWelcome(
                    $usuario,
                    $passwordPlano,
                    $this->appUri
                );

                $welcomeYaEncolado = true;
                $passwordPlano = null;
            }
        }

        // PASSWORD RESET
        if ($data->debeCambiarPassword === true && !$welcomeYaEncolado) {
            $passwordPlano = $this->generateRandomPassword();

            $usuario->setPassword(
                $this->passwordHasher->hashPassword($usuario, $passwordPlano)
            );

            $usuario->setDebeCambiarPassword(true);
        } elseif ($data->debeCambiarPassword !== null && !$welcomeYaEncolado) {
            $usuario->setDebeCambiarPassword((bool) $data->debeCambiarPassword);
        }

        // CARGOS
        if (is_array($data->cargos)) {
            $this->syncCargos($usuario, $data->cargos);
        }

        // RELACIONES
        if (is_array($data->relacionUsuarios)) {
            foreach ($usuario->getRelacionesOrigen()->toArray() as $rel) {
                $usuario->removeRelacionOrigen($rel);
                $this->entityManager->remove($rel);
            }

            foreach ($this->crearRelacionesUsuario($usuario, $data->relacionUsuarios) as $rel) {
                $usuario->addRelacionOrigen($rel);
                $this->entityManager->persist($rel);
            }
        }

        $this->entityManager->flush();

        if ($passwordPlano && $usuario->getEmail()) {
            $this->emailQueueService->enqueueUserWelcome($usuario, $passwordPlano, $this->appUri);
            return new AdminUsuarioOutput($usuario, null);
        }

        return new AdminUsuarioOutput($usuario, $passwordPlano);
    }

    /* ================= CARGOS ================= */

    private function syncCargos(Usuario $usuario, array $cargos): void
    {
        $temporada = $this->resolveTemporadaActualParaUsuario($usuario);

        $actuales = [];
        foreach ($usuario->getCargosTemporada() as $utc) {
            if ((string)$utc->getTemporada()?->getId() !== (string)$temporada->getId()) {
                continue;
            }

            $id = $utc->getEntidadCargo()?->getId();
            if ($id) {
                $actuales[$id] = $utc;
            }
        }

        $nuevos = [];
        foreach ($cargos as $ref) {
            $entidadCargo = $this->resolveEntidadCargoFromInput($usuario, $ref);
            if ($entidadCargo && $entidadCargo->getId()) {
                $nuevos[$entidadCargo->getId()] = $entidadCargo;
            }
        }

        foreach ($actuales as $id => $utc) {
            if (!isset($nuevos[$id])) {
                $usuario->removeCargoTemporada($utc);
                $this->entityManager->remove($utc);
            }
        }

        foreach ($nuevos as $id => $entidadCargo) {
            if (!isset($actuales[$id])) {
                $utc = new UsuarioTemporadaCargo();
                $utc->setUsuario($usuario);
                $utc->setTemporada($temporada);
                $utc->setEntidadCargo($entidadCargo);

                $usuario->addCargoTemporada($utc);
                $this->entityManager->persist($utc);
            }
        }
    }

    private function crearCargosTemporada(Usuario $usuario, array $cargos): array
    {
        $temporada = $this->resolveTemporadaActualParaUsuario($usuario);
        $result = [];
        $seen = [];

        foreach ($cargos as $ref) {
            $entidadCargo = $this->resolveEntidadCargoFromInput($usuario, $ref);

            if (!$entidadCargo || !$entidadCargo->getId()) {
                continue;
            }

            if (isset($seen[$entidadCargo->getId()])) {
                continue;
            }

            $seen[$entidadCargo->getId()] = true;

            $utc = new UsuarioTemporadaCargo();
            $utc->setUsuario($usuario);
            $utc->setTemporada($temporada);
            $utc->setEntidadCargo($entidadCargo);

            $result[] = $utc;
        }

        return $result;
    }

    private function resolveEntidadCargoFromInput(Usuario $usuario, string|array|null $ref): ?EntidadCargo
    {
        $id = $this->extractId($ref);
        if (!$id) return null;

        $entidadCargo = $this->entityManager->find(EntidadCargo::class, $id);

        if ($entidadCargo && (string)$entidadCargo->getEntidad()?->getId() === (string)$usuario->getEntidad()?->getId()) {
            return $entidadCargo;
        }

        return null;
    }

    private function crearRelacionesUsuario(Usuario $usuario, array $relaciones): array
    {
        $result = [];
        $seen = [];

        foreach ($relaciones as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = $this->extractId($item['usuario'] ?? $item['usuario_id'] ?? null);
            $tipoRaw = strtolower(trim((string) ($item['tipoRelacion'] ?? '')));
            $tipo = TipoRelacionEnum::tryFrom($tipoRaw);

            if (!$id || !$tipo) {
                continue;
            }

            $key = $id . '|' . $tipo->value;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $destino = $this->entityManager->find(Usuario::class, $id);

            if (!$destino instanceof Usuario) {
                continue;
            }

            if ((string) $destino->getId() === (string) $usuario->getId()) {
                continue;
            }

            $rel = new RelacionUsuario();
            $rel->setUsuarioOrigen($usuario);
            $rel->setUsuarioDestino($destino);
            $rel->setTipoRelacion($tipo);

            $result[] = $rel;
        }

        return $result;
    }

    /* ================= UTILS ================= */

    private function applyCommon(
        Usuario $usuario,
        AdminCreateUsuarioInput|AdminUpdateUsuarioInput $data,
        bool $isCreate
    ): void {
        if ($isCreate || $data->nombre !== null) {
            $usuario->setNombre(trim((string) $data->nombre));
        }

        if ($isCreate || $data->apellidos !== null) {
            $usuario->setApellidos(trim((string) $data->apellidos));
        }

        if ($isCreate || $data->email !== null) {
            $usuario->setEmail($this->normalizeNullable($data->email));
        }

        if ($isCreate || $data->telefono !== null) {
            $usuario->setTelefono($this->normalizeNullable($data->telefono));
        }

        if ($isCreate || $data->documentoIdentidad !== null) {
            $documento = $this->normalizeNullable($data->documentoIdentidad);

            if ($documento === null) {
                throw new BadRequestHttpException('El documento de identidad es obligatorio');
            }

            $usuario->setDocumentoIdentidad(mb_strtoupper($documento));
        }

        if ($isCreate || $data->activo !== null) {
            $usuario->setActivo((bool) $data->activo);
        }

        if ($isCreate || $data->motivoBajaCenso !== null) {
            $usuario->setMotivoBajaCenso(
                $usuario->isActivo()
                    ? null
                    : $this->normalizeNullable($data->motivoBajaCenso)
            );
        }

        if ($isCreate || $data->fechaNacimiento !== null) {
            try {
                $usuario->setFechaNacimiento(
                    $data->fechaNacimiento
                        ? new \DateTimeImmutable($data->fechaNacimiento)
                        : null
                );
            } catch (\Throwable) {
                throw new BadRequestHttpException('La fecha de nacimiento no es válida.');
            }
        }

        if ($isCreate || $data->antiguedad !== null) {
            $usuario->setAntiguedad($data->antiguedad !== null ? (int) $data->antiguedad : null);
        }

        if ($isCreate || $data->antiguedadReal !== null) {
            $usuario->setAntiguedadReal($data->antiguedadReal !== null ? (int) $data->antiguedadReal : null);
        }

        if ($isCreate || $data->formaPagoPreferida !== null) {
            $usuario->setFormaPagoPreferida(
                $data->formaPagoPreferida
                    ? MetodoPagoEnum::fromInput($data->formaPagoPreferida)
                    : null
            );
        }

        if ($isCreate || $data->roles !== null) {
            $usuario->setRoles($this->normalizeRoles($data->roles ?? []));
        }

        if ($isCreate || $data->debeCambiarPassword !== null) {
            $usuario->setDebeCambiarPassword((bool) $data->debeCambiarPassword);
        }
    }

    private function resolveTemporadaActualParaUsuario(Usuario $usuario): TemporadaEntidad
    {
        $entidad = $usuario->getEntidad();
        if (!$entidad) throw new BadRequestHttpException('Sin entidad');

        $codigo = $entidad->getTemporadaActual() ?? date('Y');

        $temporada = $this->temporadaEntidadRepository
            ->findOneByEntidadAndCodigo($entidad, $codigo);

        if ($temporada) return $temporada;

        $temporada = new TemporadaEntidad();
        $temporada->setEntidad($entidad);
        $temporada->setCodigo($codigo);
        $temporada->setNombre('Temporada ' . $codigo);

        [$inicio, $fin] = $this->calcularRangoDesdeCodigo($codigo, $usuario);
        $temporada->setFechaInicio($inicio);
        $temporada->setFechaFin($fin);

        $this->entityManager->persist($temporada);

        return $temporada;
    }

    private function extractId(string|array|null $value): ?string
    {
        if (!$value) return null;
        if (is_array($value)) return $value['id'] ?? $value['@id'] ?? null;
        return str_contains($value, '/') ? basename($value) : $value;
    }

    private function normalizeNullable(?string $value): ?string
    {
        return ($value = trim((string)$value)) === '' ? null : $value;
    }

    private function normalizeRoles(array $roles): array
    {
        $roles = array_values(array_unique(array_filter(array_map(
            static fn ($role) => strtoupper(trim((string) $role)),
            $roles
        ))));

        $allowed = [
            'ROLE_ADMIN',
            'ROLE_ADMIN_ENTIDAD',
            'ROLE_USER',
        ];

        $roles = array_values(array_intersect($roles, $allowed));

        if ($roles === []) {
            $roles = ['ROLE_USER'];
        }

        if (!in_array('ROLE_USER', $roles, true)) {
            $roles[] = 'ROLE_USER';
        }

        return $roles;
    }

    private function getAdmin(): Usuario
    {
        $user = $this->security->getUser();
        if (!$user instanceof Usuario) throw new AccessDeniedHttpException('No autenticado');
        return $user;
    }

    private function assertCanEdit(Usuario $admin, Usuario $usuario): void
    {
        if ((string)$admin->getEntidad()?->getId() !== (string)$usuario->getEntidad()?->getId()) {
            throw new AccessDeniedHttpException('No puedes editar este usuario');
        }
    }

    private function generateRandomPassword(int $length = 12): string
    {

        return substr(rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='), 0, $length) . 'A1!';
    }

    private function calcularRangoDesdeCodigo(string $codigo, Usuario $usuario): array
    {
        $entidad = $usuario->getEntidad();

        if (!$entidad) {
            throw new BadRequestHttpException('El usuario no tiene entidad.');
        }

        $year = (int) $codigo;

        $inicioMes = $entidad->getTemporadaInicioMes();
        $inicioDia = $entidad->getTemporadaInicioDia();

        $inicio = new \DateTimeImmutable(sprintf(
            '%04d-%02d-%02d',
            $year,
            $inicioMes,
            $inicioDia
        ));

        $fin = $inicio
            ->modify('+1 year')
            ->modify('-1 day');

        return [$inicio, $fin];
    }
}
