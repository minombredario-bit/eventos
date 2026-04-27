<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\AdminUpdateUsuarioInput;
use App\Entity\Cargo;
use App\Entity\CargoMaster;
use App\Entity\EntidadCargo;
use App\Entity\RelacionUsuario;
use App\Entity\TemporadaEntidad;
use App\Entity\Usuario;
use App\Entity\UsuarioTemporadaCargo;
use App\Enum\MetodoPagoEnum;
use App\Enum\TipoRelacionEnum;
use App\Repository\CargoRepository;
use App\Repository\TemporadaEntidadRepository;
use App\Service\EmailQueueService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class AdminUpdateUsuarioProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly TemporadaEntidadRepository $temporadaEntidadRepository,
        private readonly CargoRepository $cargoRepository,
        private readonly EmailQueueService $emailQueueService,
        private readonly string $appUri,
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

        // If the admin is an entity-admin, restrict modifications to users of the same entity
        if (in_array('ROLE_ADMIN_ENTIDAD', $admin->getRoles(), true) && !in_array('ROLE_SUPERADMIN', $admin->getRoles(), true)) {
            $adminEntidad = $admin->getEntidad();
            if ($adminEntidad === null) {
                throw new AccessDeniedHttpException('El administrador no tiene entidad asignada.');
            }
            // we'll check the target user after loading it below
            $restrictToSameEntity = true;
        } else {
            $restrictToSameEntity = false;
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

        if (!empty($restrictToSameEntity)) {
            $adminEntidadId = $admin->getEntidad()?->getId();
            $targetEntidadId = $usuario->getEntidad()?->getId();
            if ($adminEntidadId === null || $targetEntidadId === null || (string)$adminEntidadId !== (string)$targetEntidadId) {
                throw new AccessDeniedHttpException('No puedes modificar usuarios de otra entidad.');
            }
        }

        $this->entityManager->beginTransaction();

        try {
            $emailAnterior = $this->normalizeNullableString($usuario->getEmail());

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
            if ($data->aceptoLopd !== null) {
                $usuario->setAceptoLopd((bool) $data->aceptoLopd);
            }
            if ($data->roles !== null) {
                $usuario->setRoles($this->normalizeRoles($data->roles));
            }
            if ($data->email !== null) {
                $email = $this->normalizeNullableString($data->email);
                if ($email === null) {
                    throw new BadRequestHttpException('El email no puede estar vacío.');
                }

                $emailNuevo = mb_strtolower($email);
                $usuario->setEmail($emailNuevo);

                if ($emailAnterior !== null && $emailAnterior !== $emailNuevo) {
                    $this->emailQueueService->enqueueUserEmailChanged(
                        $usuario,
                        $emailAnterior,
                        $emailNuevo,
                        $this->appUri,
                    );
                }
            }

            // Sync cargosTemporada if cargos provided: keep existing cargo IDs and only
            // add/remove the differences so we do not reinsert an already existing row.
            if (is_array($data->cargos)) {
                $temporada = $this->resolveTemporadaParaUsuario($usuario, $data->temporada ?? null);

                $cargosActuales = [];
                foreach (iterator_to_array($usuario->getCargosTemporada()) as $utc) {
                    if ($utc->getTemporada()?->getId() !== $temporada->getId()) {
                        continue;
                    }

                    // UsuarioTemporadaCargo ahora apunta a EntidadCargo; resolver el cargo operativo (Cargo) si es posible
                    $entidadCargoUtc = $utc->getEntidadCargo();

                    $cargoActualId = null;

                    if ($entidadCargoUtc?->getCargo() instanceof Cargo) {
                        $cargoActualId = $entidadCargoUtc->getCargo()->getId();
                    } elseif ($entidadCargoUtc?->getCargoMaster() !== null) {
                        // intentar resolver un Cargo operativo existente para la entidad por codigo
                        $codigo = $entidadCargoUtc->getCargoMaster()->getCodigo();
                        if ($codigo !== null) {
                            $existingCargo = $this->cargoRepository->findOneBy([
                                'entidad' => $usuario->getEntidad(),
                                'codigo' => $codigo,
                            ]);
                            if ($existingCargo instanceof Cargo) {
                                $cargoActualId = $existingCargo->getId();
                            }
                        }
                    }

                    if ($cargoActualId === null) {
                        continue;
                    }

                    $cargosActuales[$cargoActualId] = $utc;
                }

                $cargosSolicitados = [];
                foreach ($data->cargos as $cargoRef) {
                    $cargo = $this->resolveCargoOperativo($usuario, $cargoRef);
                    if ($cargo === null) {
                        continue;
                    }

                    $cargoId = $cargo->getId();
                    if ($cargoId === null || isset($cargosSolicitados[$cargoId])) {
                        continue;
                    }

                    $cargosSolicitados[$cargoId] = $cargo;
                }

                foreach ($cargosActuales as $cargoId => $utc) {
                    if (isset($cargosSolicitados[$cargoId])) {
                        continue;
                    }

                    $usuario->removeCargoTemporada($utc);
                    $this->entityManager->remove($utc);
                }

                foreach ($cargosSolicitados as $cargoId => $cargo) {
                    if (isset($cargosActuales[$cargoId])) {
                        continue;
                    }

                    $cargoTemporada = new UsuarioTemporadaCargo();
                    $cargoTemporada->setUsuario($usuario);
                    $cargoTemporada->setTemporada($temporada);

                    // Resolver o crear EntidadCargo para el cargo operativo
                    $entidadCargo = $this->entityManager->getRepository(EntidadCargo::class)->findOneBy([
                        'entidad' => $usuario->getEntidad(),
                        'cargo' => $cargo,
                    ]);

                    if (!$entidadCargo instanceof EntidadCargo) {
                        // Intentar buscar por cargoMaster (código)
                        $codigo = $cargo->getCodigo();
                        if ($codigo !== null) {
                            $cargoMaster = $this->entityManager->getRepository(CargoMaster::class)->findOneBy(['codigo' => $codigo]);
                            if ($cargoMaster instanceof CargoMaster) {
                                $entidadCargo = $this->entityManager->getRepository(EntidadCargo::class)->findOneBy([
                                    'entidad' => $usuario->getEntidad(),
                                    'cargoMaster' => $cargoMaster,
                                ]);
                            }
                        }
                    }

                    if (!$entidadCargo instanceof EntidadCargo) {
                        // Crear EntidadCargo local para este cargo operativo
                        $entidadCargo = new EntidadCargo();
                        $entidadCargo->setEntidad($usuario->getEntidad());
                        $entidadCargo->setCargo($cargo);
                        $entidadCargo->setNombre(null);
                        $entidadCargo->setOrden(null);
                        $entidadCargo->setActivo(true);
                        $this->entityManager->persist($entidadCargo);
                    }

                    $cargoTemporada->setEntidadCargo($entidadCargo);

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
            $this->entityManager->commit();
        } catch (\Throwable $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }

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

    /** @param array<int, string|array<string, mixed>> $cargos */
    private function crearCargosTemporada(Usuario $usuario, array $cargos, ?string $temporadaInput = null): array
    {
        $result = [];
        $seenCargoIds = [];
        $temporada = $this->resolveTemporadaParaUsuario($usuario, $temporadaInput);

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
            $cargoTemporada->setTemporada($temporada);

            // Resolver o crear EntidadCargo para este cargo operativo
            $entidadCargo = $this->entityManager->getRepository(EntidadCargo::class)->findOneBy([
                'entidad' => $usuario->getEntidad(),
                'cargo' => $cargo,
            ]);

            if (!$entidadCargo instanceof EntidadCargo) {
                $codigo = $cargo->getCodigo();
                if ($codigo !== null) {
                    $cargoMaster = $this->entityManager->getRepository(CargoMaster::class)->findOneBy(['codigo' => $codigo]);
                    if ($cargoMaster instanceof CargoMaster) {
                        $entidadCargo = $this->entityManager->getRepository(EntidadCargo::class)->findOneBy([
                            'entidad' => $usuario->getEntidad(),
                            'cargoMaster' => $cargoMaster,
                        ]);
                    }
                }
            }

            if (!$entidadCargo instanceof EntidadCargo) {
                $entidadCargo = new EntidadCargo();
                $entidadCargo->setEntidad($usuario->getEntidad());
                $entidadCargo->setCargo($cargo);
                $entidadCargo->setNombre(null);
                $entidadCargo->setOrden(null);
                $entidadCargo->setActivo(true);
                $this->entityManager->persist($entidadCargo);
            }

            $cargoTemporada->setEntidadCargo($entidadCargo);

            $result[] = $cargoTemporada;
        }

        return $result;
    }

    private function resolveTemporadaParaUsuario(Usuario $usuario, ?string $temporadaInput = null): TemporadaEntidad
    {
        $temporada = null;

        if (!empty($temporadaInput)) {
            $tid = $this->extractId($temporadaInput);
            if ($tid !== null) {
                $temporada = $this->entityManager->find(TemporadaEntidad::class, $tid)
                    ?? $this->temporadaEntidadRepository->findOneByEntidadAndCodigo($usuario->getEntidad(), $tid);

                if ($temporada === null && preg_match('/^\d{4}$/', $tid)) {
                    $seasonCode = $this->seasonCodeFromYear((int) $tid);
                    $temporada = $this->temporadaEntidadRepository->findOneByEntidadAndCodigo($usuario->getEntidad(), $seasonCode);
                }
            }
        }

        if ($temporada === null) {
            $now = new \DateTimeImmutable();
            $startMonth = $usuario->getEntidad()->getTemporadaInicioMes() ?? 1;
            $startDay = $usuario->getEntidad()->getTemporadaInicioDia() ?? 1;
            $currentYear = (int) $now->format('Y');

            // If current month is before startMonth, the season started the previous year
            $nowMonth = (int) $now->format('n');
            if ($nowMonth >= $startMonth) {
                $seasonYear = $currentYear;
            } else {
                $seasonYear = $currentYear - 1;
            }

            $seasonCode = $this->seasonCodeFromYear($seasonYear);
            $temporada = $this->temporadaEntidadRepository->findOneByEntidadAndCodigo($usuario->getEntidad(), $seasonCode);
            if ($temporada === null) {
                // create temporada for computed season code and populate fechaInicio/fechaFin according to entidad rules
                $temporada = new TemporadaEntidad();
                $temporada->setEntidad($usuario->getEntidad());
                $temporada->setCodigo($seasonCode);
                $temporada->setNombre('Temporada ' . $seasonCode);

                // calculate fechaInicio as seasonYear-startMonth-01 and fechaFin as one year minus one day
                $fechaInicio = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $seasonYear, $startMonth, $startDay));
                $fechaFin = $fechaInicio->modify('+1 year')->modify('-1 day');
                $temporada->setFechaInicio($fechaInicio);
                $temporada->setFechaFin($fechaFin);

                $this->entityManager->persist($temporada);
            }
        }

        return $temporada;
    }

    /** @param array<int, array{usuario?: string, usuario_id?: string, tipoRelacion?: string}> $relacionUsuarios */
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

        // 1) Si viene /api/cargos/{id}
        $cargo = $this->cargoRepository->findOneByIdAndEntidad($usuario->getEntidad(), $cargoId);

        if ($cargo instanceof Cargo) {
            return $cargo;
        }

        // 2) Si viene /api/entidad_cargos/{id}
        $entidadCargo = $this->entityManager->find(EntidadCargo::class, $cargoId);

        if (!$entidadCargo instanceof EntidadCargo) {
            return null;
        }

        if ((string) $entidadCargo->getEntidad()?->getId() !== (string) $usuario->getEntidad()->getId()) {
            return null;
        }

        // Cargo personalizado
        if ($entidadCargo->getCargo() instanceof Cargo) {
            return $entidadCargo->getCargo();
        }

        // Cargo oficial basado en CargoMaster: crear/reutilizar Cargo operativo para la entidad
        $cargoMaster = $entidadCargo->getCargoMaster();

        if ($cargoMaster === null) {
            return null;
        }

        $existingCargo = $this->cargoRepository->findOneBy([
            'entidad' => $usuario->getEntidad(),
            'codigo' => $cargoMaster->getCodigo(),
        ]);

        if ($existingCargo instanceof Cargo) {
            return $existingCargo;
        }

        $cargo = new Cargo();
        $cargo->setEntidad($usuario->getEntidad());
        $cargo->setNombre($cargoMaster->getNombre());
        $cargo->setCodigo($cargoMaster->getCodigo());
        $cargo->setDescripcion($cargoMaster->getDescripcion());
        $cargo->setActivo(true);
        $cargo->setInfantilEspecial($cargoMaster->isInfantilEspecial());
        $cargo->setComputaComoDirectivo($cargoMaster->isComputaComoDirectivo());
        $cargo->setEsRepresentativo($cargoMaster->isEsRepresentativo());
        $cargo->setEsInfantil($cargoMaster->isEsInfantil());
        $cargo->setOrdenJerarquico($cargoMaster->getOrdenJerarquico());

        $this->entityManager->persist($cargo);

        return $cargo;
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

