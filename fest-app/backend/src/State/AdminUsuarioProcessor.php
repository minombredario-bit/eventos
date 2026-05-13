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

        foreach ($this->crearRelacionesUsuarioBidireccionales($usuario, $data->relacionUsuarios ?? []) as $rel) {
            $this->entityManager->persist($rel);
        }

        $this->entityManager->persist($usuario);
        $this->entityManager->flush();
        $this->entityManager->refresh($usuario);

        if ($usuario->getEmail()) {
            $this->emailQueueService->enqueueUserWelcome($usuario, $passwordPlano, $this->appUri);
            return new AdminUsuarioOutput($usuario, null);
        }

        return new AdminUsuarioOutput($usuario, $passwordPlano);
    }

    /* ================= UPDATE ================= */

    private function handleUpdate(AdminUpdateUsuarioInput $data, Usuario $admin, array $uriVariables): AdminUsuarioOutput
    {
        $usuario = $this->entityManager->find(Usuario::class, $uriVariables['id'] ?? null);

        if (!$usuario instanceof Usuario) {
            throw new BadRequestHttpException('Usuario no encontrado');
        }

        $this->assertCanEdit($admin, $usuario);

        $this->applyCommon($usuario, $data, false);

        if (is_array($data->relacionUsuarios)) {
            $this->syncRelacionesBidireccionales($usuario, $data->relacionUsuarios);
        }

        $this->entityManager->flush();

        return new AdminUsuarioOutput($usuario, null);
    }

    /* ================= RELACIONES ================= */

    private function syncRelacionesBidireccionales(Usuario $usuario, array $relaciones): void
    {
        $deseadas = $this->normalizarRelacionesDeseadas($usuario, $relaciones);

        $relacionesActuales = $this->entityManager
            ->getRepository(RelacionUsuario::class)
            ->createQueryBuilder('r')
            ->where('r.usuarioOrigen = :usuario OR r.usuarioDestino = :usuario')
            ->setParameter('usuario', $usuario)
            ->getQuery()
            ->getResult();

        foreach ($relacionesActuales as $rel) {
            if (!$rel instanceof RelacionUsuario) {
                continue;
            }

            $origen = $rel->getUsuarioOrigen();
            $destino = $rel->getUsuarioDestino();

            if (!$origen || !$destino) {
                continue;
            }

            $otro = (string) $origen->getId() === (string) $usuario->getId()
                ? $destino
                : $origen;

            $key = $otro->getId() . '|' . $rel->getTipoRelacion()->value;

            if (!isset($deseadas[$key])) {
                $this->eliminarRelacionBidireccional($usuario, $otro, $rel->getTipoRelacion());
            }
        }

        foreach ($deseadas as $data) {
            $this->crearRelacionSiNoExiste($usuario, $data['usuario'], $data['tipo']);
            $this->crearRelacionSiNoExiste($data['usuario'], $usuario, $data['tipo']);
        }
    }

    private function eliminarRelacionBidireccional(
        Usuario $usuarioA,
        Usuario $usuarioB,
        TipoRelacionEnum $tipo
    ): void {
        $relaciones = $this->entityManager
            ->getRepository(RelacionUsuario::class)
            ->createQueryBuilder('r')
            ->where(
                '(r.usuarioOrigen = :a AND r.usuarioDestino = :b)
             OR
             (r.usuarioOrigen = :b AND r.usuarioDestino = :a)'
            )
            ->andWhere('r.tipoRelacion = :tipo')
            ->setParameter('a', $usuarioA)
            ->setParameter('b', $usuarioB)
            ->setParameter('tipo', $tipo)
            ->getQuery()
            ->getResult();

        foreach ($relaciones as $relacion) {
            $this->entityManager->remove($relacion);
        }
    }

    private function crearRelacionesUsuarioBidireccionales(Usuario $usuario, array $relaciones): array
    {
        $result = [];
        $deseadas = $this->normalizarRelacionesDeseadas($usuario, $relaciones);

        foreach ($deseadas as $data) {
            $result[] = $this->buildRelacion($usuario, $data['usuario'], $data['tipo']);
            $result[] = $this->buildRelacion($data['usuario'], $usuario, $data['tipo']);
        }

        return array_filter($result);
    }

    private function normalizarRelacionesDeseadas(Usuario $usuario, array $relaciones): array
    {
        $result = [];

        foreach ($relaciones as $item) {
            if (!is_array($item)) continue;

            $id = $this->extractId($item['usuario'] ?? $item['usuario_id'] ?? null);
            $tipo = TipoRelacionEnum::tryFrom(strtolower(trim((string)($item['tipoRelacion'] ?? ''))));

            if (!$id || !$tipo) continue;

            if ((string)$id === (string)$usuario->getId()) continue;

            $destino = $this->entityManager->find(Usuario::class, $id);

            if (!$destino instanceof Usuario) continue;

            $key = $destino->getId() . '|' . $tipo->value;

            if (isset($result[$key])) continue;

            $result[$key] = [
                'usuario' => $destino,
                'tipo' => $tipo,
            ];
        }

        return $result;
    }

    private function crearRelacionSiNoExiste(Usuario $origen, Usuario $destino, TipoRelacionEnum $tipo): void
    {
        if ($origen->getId() === $destino->getId()) return;

        $repo = $this->entityManager->getRepository(RelacionUsuario::class);

        if ($repo->findOneBy([
            'usuarioOrigen' => $origen,
            'usuarioDestino' => $destino,
            'tipoRelacion' => $tipo,
        ])) {
            return;
        }

        $this->entityManager->persist($this->buildRelacion($origen, $destino, $tipo));
    }

    private function buildRelacion(Usuario $origen, Usuario $destino, TipoRelacionEnum $tipo): RelacionUsuario
    {
        $rel = new RelacionUsuario();
        $rel->setUsuarioOrigen($origen);
        $rel->setUsuarioDestino($destino);
        $rel->setTipoRelacion($tipo);

        return $rel;
    }

    /* ================= CARGOS ================= */

    private function syncCargos(Usuario $usuario, array $cargosInput): void
    {
        $temporada = $this->findTemporadaActiva($usuario);

        $deseadosIds = [];

        foreach ($cargosInput as $item) {
            if (is_string($item)) {
                $id = $this->extractId($item);
            } elseif (is_array($item)) {
                $id = $this->extractId($item['@id'] ?? $item['id'] ?? null);
            } else {
                continue;
            }

            if ($id) {
                $deseadosIds[$id] = true;
            }
        }

        foreach ($usuario->getCargosTemporada()->toArray() as $utc) {
            $entidadCargo = $utc->getEntidadCargo();
            $entidadCargoId = $entidadCargo?->getId();

            if (!$entidadCargoId || !isset($deseadosIds[$entidadCargoId])) {
                $usuario->removeCargoTemporada($utc);
                $this->entityManager->remove($utc);
            }
        }

        $existentesIds = [];

        foreach ($usuario->getCargosTemporada() as $utc) {
            $entidadCargoId = $utc->getEntidadCargo()?->getId();

            if ($entidadCargoId) {
                $existentesIds[$entidadCargoId] = true;
            }
        }

        foreach (array_keys($deseadosIds) as $id) {
            if (isset($existentesIds[$id])) {
                continue;
            }

            $entidadCargo = $this->entityManager->find(EntidadCargo::class, $id);

            if (!$entidadCargo instanceof EntidadCargo) {
                continue;
            }

            $utc = new UsuarioTemporadaCargo();
            $utc->setEntidadCargo($entidadCargo);
            $utc->setTemporada($temporada);

            $usuario->addCargoTemporada($utc);

            $this->entityManager->persist($utc);

            $existentesIds[$id] = true;
        }
    }

    /**
     * Deriva la temporada activa a partir del día/mes de inicio configurado en la entidad.
     * Calcula el inicio del ciclo actual y busca la TemporadaEntidad no cerrada que lo contenga.
     * Si ninguna coincide exactamente, devuelve la más reciente no cerrada como fallback.
     */
    private function findTemporadaActiva(Usuario $usuario): ?TemporadaEntidad
    {
        $entidad = $usuario->getEntidad();
        $hoy     = new \DateTimeImmutable('today');
        $mes     = $entidad->getTemporadaInicioMes();
        $dia     = $entidad->getTemporadaInicioDia();

        // Inicio del ciclo de temporada en el año actual
        $inicioEsteAnio = \DateTimeImmutable::createFromFormat(
            'Y-n-j',
            $hoy->format('Y') . "-$mes-$dia"
        );

        // Si hoy aún no hemos llegado al inicio del ciclo, la temporada arrancó el año pasado
        $inicioTemporada = $hoy >= $inicioEsteAnio
            ? $inicioEsteAnio
            : $inicioEsteAnio->modify('-1 year');

        $qb = $this->temporadaEntidadRepository->createQueryBuilder('t')
            ->where('t.entidad = :entidad')
            ->andWhere('t.cerrada = false')
            ->setParameter('entidad', $entidad)
            ->orderBy('t.fechaInicio', 'DESC')
            ->setMaxResults(1);

        // 1. Coincidencia exacta: la temporada cuyo rango contiene el inicio del ciclo actual
        $exacta = (clone $qb)
            ->andWhere('t.fechaInicio <= :inicio')
            ->andWhere('t.fechaFin >= :inicio')
            ->setParameter('inicio', $inicioTemporada)
            ->getQuery()
            ->getOneOrNullResult();

        if ($exacta !== null) {
            return $exacta;
        }

        // 2. Fallback: la temporada no cerrada más reciente de la entidad
        return $qb->getQuery()->getOneOrNullResult();
    }

    /* ================= UTILS ================= */

    private function extractId(string|array|null $value): ?string
    {
        if (!$value) return null;
        if (is_array($value)) return $value['id'] ?? null;
        return str_contains($value, '/') ? basename($value) : $value;
    }

    private function applyCommon(Usuario $usuario, $data, bool $isCreate): void
    {
        if ($isCreate || $data->nombre !== null) {
            $usuario->setNombre(trim((string)$data->nombre));
        }

        if ($isCreate || $data->apellidos !== null) {
            $usuario->setApellidos(trim((string)$data->apellidos));
        }

        if ($isCreate || $data->direccion !== null) {
            $usuario->setDireccion($data->direccion);
        }

        if ($isCreate || $data->email !== null) {
            $usuario->setEmail($data->email);
        }

        if ($isCreate || $data->telefono !== null) {
            $usuario->setTelefono($data->telefono);
        }

        if ($isCreate || $data->documentoIdentidad !== null) {
            $usuario->setDocumentoIdentidad($data->documentoIdentidad);
        }

        if ($isCreate || $data->activo !== null) {
            $usuario->setActivo($data->activo ?? true);
        }

        if ($isCreate || $data->motivoBajaCenso !== null) {
            $usuario->setMotivoBajaCenso($data->motivoBajaCenso);
        }

        if ($isCreate || $data->antiguedad !== null) {
            $usuario->setAntiguedad($data->antiguedad);
        }

        if ($isCreate || $data->antiguedadReal !== null) {
            $usuario->setAntiguedadReal($data->antiguedadReal);
        }

        if ($isCreate || $data->fechaNacimiento !== null) {
            $usuario->setFechaNacimiento(
                $data->fechaNacimiento !== null
                    ? new \DateTimeImmutable($data->fechaNacimiento)
                    : null
            );
        }

        if ($isCreate || $data->formaPagoPreferida !== null) {
            $usuario->setFormaPagoPreferida(
                $data->formaPagoPreferida !== null
                    ? MetodoPagoEnum::from($data->formaPagoPreferida)
                    : null
            );
        }

        if (is_array($data->roles)) {
            $this->applyRoles($usuario, $data->roles);
        }

        if (is_array($data->cargos)) {
            $this->syncCargos($usuario, $data->cargos);
        }

        // Solo en update — en create se fuerza a true en handleCreate
        if (!$isCreate && $data->debeCambiarPassword !== null) {
            $usuario->setDebeCambiarPassword($data->debeCambiarPassword);
        }
    }

    /**
     * Aplica los roles al usuario validando que solo se permitan roles asignables
     * por un administrador de entidad (no permite escalar a ROLE_SUPERADMIN).
     */
    private function applyRoles(Usuario $usuario, array $roles): void
    {
        $allowed = ['ROLE_USER', 'ROLE_EVENTO', 'ROLE_ADMIN_ENTIDAD'];

        $sanitized = array_values(
            array_unique(
                array_filter($roles, static fn(mixed $r) => is_string($r) && in_array($r, $allowed, true))
            )
        );

        // Siempre debe existir al menos ROLE_USER
        if (!in_array('ROLE_USER', $sanitized, true)) {
            $sanitized[] = 'ROLE_USER';
        }

        $usuario->setRoles($sanitized);
    }

    private function getAdmin(): Usuario
    {
        $user = $this->security->getUser();
        if (!$user instanceof Usuario) throw new AccessDeniedHttpException();
        return $user;
    }

    private function assertCanEdit(Usuario $admin, Usuario $usuario): void
    {
        if ($admin->getEntidad()->getId() !== $usuario->getEntidad()->getId()) {
            throw new AccessDeniedHttpException();
        }
    }

    private function generateRandomPassword(): string
    {
        return substr(bin2hex(random_bytes(6)), 0, 10) . '!';
    }
}
