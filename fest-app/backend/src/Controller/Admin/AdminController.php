<?php

namespace App\Controller\Admin;

use App\Entity\Evento;
use App\Entity\Inscripcion;
use App\Entity\Usuario;
use App\Enum\CensadoViaEnum;
use App\Enum\EstadoValidacionEnum;
use App\Enum\MetodoPagoEnum;
use App\Enum\TipoRelacionEconomicaEnum;
use App\Repository\EventoRepository;
use App\Repository\InscripcionRepository;
use App\Repository\UsuarioRepository;
use App\Service\CensoImporterService;
use App\Service\EmailQueueService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly UsuarioRepository $usuarioRepository,
        private readonly EventoRepository $eventoRepository,
        private readonly InscripcionRepository $inscripcionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly CensoImporterService $censoImporter,
        private readonly EmailQueueService $emailQueueService,
    ) {}

    #[Route('/usuarios', name: 'api_admin_usuario_create', methods: ['POST'])]
    public function crearUsuario(Request $request): JsonResponse
    {
        /** @var Usuario $admin */
        $admin = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        foreach (['nombre', 'apellidos', 'email', 'password'] as $field) {
            if (empty($data[$field])) {
                return $this->json(['error' => "Campo requerido: {$field}"], 400);
            }
        }

        $existing = $this->usuarioRepository->findByEmail((string) $data['email']);
        if ($existing instanceof Usuario) {
            return $this->json(['error' => 'El email ya está registrado'], 409);
        }

        $usuario = new Usuario();
        $usuario->setEntidad($admin->getEntidad());
        $usuario->setNombre((string) $data['nombre']);
        $usuario->setApellidos((string) $data['apellidos']);
        $usuario->setEmail(strtolower(trim((string) $data['email'])));
        $usuario->setTelefono(isset($data['telefono']) ? (string) $data['telefono'] : null);
        $usuario->setRoles(['ROLE_USER']);
        $usuario->setActivo(true);
        $usuario->setCensadoVia(CensadoViaEnum::MANUAL);
        $usuario->setFechaAltaCenso(new \DateTimeImmutable());
        $usuario->setAntiguedad(isset($data['antiguedad']) ? (int) $data['antiguedad'] : null);
        $usuario->setAntiguedadReal(isset($data['antiguedadReal']) ? (int) $data['antiguedadReal'] : null);
        $usuario->setDebeCambiarPassword(true);
        $usuario->setPasswordActualizadaAt(null);

        if (array_key_exists('formaPagoPreferida', $data)) {
            $usuario->setFormaPagoPreferida(
                $data['formaPagoPreferida'] !== null
                    ? MetodoPagoEnum::from((string) $data['formaPagoPreferida'])
                    : null
            );
        }

        $hashedPassword = $this->passwordHasher->hashPassword($usuario, (string) $data['password']);
        $usuario->setPassword($hashedPassword);

        $this->entityManager->persist($usuario);
        $this->emailQueueService->enqueueUserWelcome($usuario, (string) $data['password'], 'http://localhost:4200');
        $this->entityManager->flush();

        return $this->json([
            'id' => $usuario->getId(),
            'email' => $usuario->getEmail(),
            'activo' => $usuario->isActivo(),
        ], 201);
    }

    #[Route('/usuarios/importar-excel', name: 'api_admin_usuarios_importar_excel', methods: ['POST'])]
    public function importarUsuariosExcel(Request $request): JsonResponse
    {
        /** @var Usuario $admin */
        $admin = $this->getUser();

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'Archivo no proporcionado'], 400);
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->json(['error' => 'Formato de archivo no válido. Use .xlsx o .xls'], 400);
        }

        $tempPath = sys_get_temp_dir() . '/' . uniqid('usuarios_') . '.' . $extension;
        $file->move(dirname($tempPath), basename($tempPath));

        try {
            $resultado = $this->censoImporter->importar(
                $tempPath,
                $admin->getEntidad(),
                $admin->getEntidad()->getTemporadaActual(),
                'http://localhost:4200',
            );
            @unlink($tempPath);

            return $this->json($resultado);
        } catch (\Throwable $e) {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }

            return $this->json(['error' => 'Error procesando el archivo: ' . $e->getMessage()], 500);
        }
    }


    #[Route('/usuarios/{id}/perfil', name: 'api_admin_usuario_perfil', methods: ['GET'])]
    public function usuarioPerfil(string $id): JsonResponse
    {
        /** @var Usuario $admin */
        $admin = $this->getUser();
        $usuario = $this->usuarioRepository->find($id);

        if (!$usuario instanceof Usuario) {
            return $this->json(['error' => 'Usuario no encontrado'], 404);
        }

        if ($usuario->getEntidad()->getId() !== $admin->getEntidad()->getId()) {
            return $this->json(['error' => 'Acceso denegado'], 403);
        }

        return $this->json($this->mapUsuarioAdmin($usuario));
    }

    /**
     * List pending users for the admin's entity.
     */
    #[Route('/usuarios-pendientes', name: 'api_admin_usuarios_pendientes', methods: ['GET'])]
    public function usuariosPendientes(): JsonResponse
    {
        /** @var Usuario $admin */
        $admin = $this->getUser();

        $usuarios = $this->usuarioRepository->findPendientesByEntidad($admin->getEntidad());

        $data = array_map(fn(Usuario $user) => [
            'id' => $user->getId(),
            'nombre' => $user->getNombre(),
            'apellidos' => $user->getApellidos(),
            'email' => $user->getEmail(),
            'telefono' => $user->getTelefono(),
        ], $usuarios);

        return $this->json(['hydra:member' => $data]);
    }

    /**
     * Validate a pending user.
     */
    #[Route('/usuarios/{id}/validar', name: 'api_admin_usuario_validar', methods: ['POST'])]
    public function validarUsuario(int $id, Request $request): JsonResponse
    {
        /** @var Usuario $admin */
        $admin = $this->getUser();
        $user = $this->usuarioRepository->find($id);

        if (!$user) {
            return $this->json(['error' => 'Usuario no encontrado'], 404);
        }

        // Verify same entity
        if ($user->getEntidad()->getId() !== $admin->getEntidad()->getId()) {
            return $this->json(['error' => 'Acceso denegado'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['censadoVia'])) {
            $user->setCensadoVia(CensadoViaEnum::from($data['censadoVia']));
        }

        $this->entityManager->flush();

        return $this->json([
            'id' => $user->getId(),
        ]);
    }

    /**
     * Reject a pending user.
     */
    #[Route('/usuarios/{id}/rechazar', name: 'api_admin_usuario_rechazar', methods: ['POST'])]
    public function rechazarUsuario(int $id): JsonResponse
    {
        /** @var Usuario $admin */
        $admin = $this->getUser();
        $user = $this->usuarioRepository->find($id);

        if (!$user) {
            return $this->json(['error' => 'Usuario no encontrado'], 404);
        }

        // Verify same entity
        if ($user->getEntidad()->getId() !== $admin->getEntidad()->getId()) {
            return $this->json(['error' => 'Acceso denegado'], 403);
        }

        $this->entityManager->flush();

        return $this->json([
            'id' => $user->getId(),
        ]);
    }

    /**
     * Give user census access.
     */
    #[Route('/usuarios/{id}/alta-censo', name: 'api_admin_usuario_alta_censo', methods: ['POST'])]
    public function altaCenso(int $id): JsonResponse
    {
        /** @var Usuario $admin */
        $admin = $this->getUser();
        $user = $this->usuarioRepository->find($id);

        if (!$user) {
            return $this->json(['error' => 'Usuario no encontrado'], 404);
        }

        // Verify same entity
        if ($user->getEntidad()->getId() !== $admin->getEntidad()->getId()) {
            return $this->json(['error' => 'Acceso denegado'], 403);
        }

        $user->setFechaAltaCenso(new \DateTimeImmutable());
        $user->setActivo(true);
        $this->entityManager->flush();

        return $this->json([
            'id' => $user->getId(),
            'fechaAltaCenso' => $user->getFechaAltaCenso()?->format('c'),
        ]);
    }

    /**
     * Remove user from census.
     */
    #[Route('/usuarios/{id}/baja-censo', name: 'api_admin_usuario_baja_censo', methods: ['POST'])]
    public function bajaCenso(int $id, Request $request): JsonResponse
    {
        /** @var Usuario $admin */
        $admin = $this->getUser();
        $user = $this->usuarioRepository->find($id);

        if (!$user) {
            return $this->json(['error' => 'Usuario no encontrado'], 404);
        }

        // Verify same entity
        if ($user->getEntidad()->getId() !== $admin->getEntidad()->getId()) {
            return $this->json(['error' => 'Acceso denegado'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $user->setFechaBajaCenso(new \DateTimeImmutable());
        $user->setMotivoBajaCenso($data['motivo'] ?? null);
        $user->setActivo(false);
        $this->entityManager->flush();

        return $this->json([
            'id' => $user->getId(),
            'esCensadoInterno' => $user->isEsCensadoInterno(),
            'fechaBajaCenso' => $user->getFechaBajaCenso()?->format('c'),
        ]);
    }

    /**
     * List inscriptions for the admin's entity.
     */
    #[Route('/inscripciones', name: 'api_admin_inscripciones_list', methods: ['GET'])]
    public function inscripciones(Request $request): JsonResponse
    {
        /** @var Usuario $admin */
        $admin = $this->getUser();
        $eventoId = $request->query->get('evento');

        if ($eventoId) {
            $evento = $this->eventoRepository->find($eventoId);
            if ($evento && $evento->getEntidad()->getId() === $admin->getEntidad()->getId()) {
                $inscripciones = $this->inscripcionRepository->findByEvento($evento);
            } else {
                return $this->json(['error' => 'Evento no encontrado'], 404);
            }
        } else {
            $eventos = $this->eventoRepository->findAllByEntidad($admin->getEntidad());
            $inscripciones = [];
            foreach ($eventos as $evento) {
                $inscripciones = array_merge(
                    $inscripciones,
                    $this->inscripcionRepository->findByEvento($evento)
                );
            }
        }

        $data = array_map(fn(Inscripcion $inscripcion) => [
            'id' => $inscripcion->getId(),
            'codigo' => $inscripcion->getCodigo(),
            'usuario' => [
                'id' => $inscripcion->getUsuario()->getId(),
                'nombre' => $inscripcion->getUsuario()->getNombre(),
                'apellidos' => $inscripcion->getUsuario()->getApellidos(),
                'email' => $inscripcion->getUsuario()->getEmail(),
            ],
            'evento' => [
                'id' => $inscripcion->getEvento()->getId(),
                'titulo' => $inscripcion->getEvento()->getTitulo(),
            ],
            'estadoInscripcion' => $inscripcion->getEstadoInscripcion()->value,
            'estadoPago' => $inscripcion->getEstadoPago()->value,
            'importeTotal' => $inscripcion->getImporteTotal(),
            'importePagado' => $inscripcion->getImportePagado(),
            'createdAt' => $inscripcion->getCreatedAt()->format('c'),
        ], $inscripciones);

        return $this->json(['hydra:member' => $data]);
    }

    /**
     * Get event registration summary.
     */
    #[Route('/eventos/{id}/reporte-resumen', name: 'api_admin_evento_reporte_resumen', methods: ['GET'])]
    public function reporteResumen(int $id): JsonResponse
    {
        /** @var Usuario $admin */
        $admin = $this->getUser();
        $evento = $this->eventoRepository->find($id);

        if (!$evento) {
            return $this->json(['error' => 'Evento no encontrado'], 404);
        }

        if ($evento->getEntidad()->getId() !== $admin->getEntidad()->getId()) {
            return $this->json(['error' => 'Acceso denegado'], 403);
        }

        $inscripciones = $this->inscripcionRepository->findByEvento($evento);

        // Build summary by actividad
        $resumen = [];
        foreach ($evento->getActividades() as $actividad) {
            $resumen[$actividad->getNombre()] = [
                'actividad' => $actividad->getNombre(),
                'tipo' => $actividad->getTipoActividad()->value,
                'adultos' => 0,
                'infantiles' => 0,
                'total' => 0,
            ];
        }

        foreach ($inscripciones as $inscripcion) {
            if ($inscripcion->getEstadoInscripcion()->value === 'cancelada') {
                continue;
            }

            foreach ($inscripcion->getLineas() as $linea) {
                if ($linea->getEstadoLinea()->value === 'cancelada') {
                    continue;
                }

                $actividadNombre = $linea->getNombreActividadSnapshot();
                if (isset($resumen[$actividadNombre])) {
                    $tipoPersona = $linea->getTipoPersonaSnapshot();
                    if ($tipoPersona === 'infantil') {
                        $resumen[$actividadNombre]['infantiles']++;
                    } else {
                        $resumen[$actividadNombre]['adultos']++;
                    }
                    $resumen[$actividadNombre]['total']++;
                }
            }
        }

        return $this->json([
            'evento' => [
                'id' => $evento->getId(),
                'titulo' => $evento->getTitulo(),
                'fecha' => $evento->getFechaEvento()->format('Y-m-d'),
            ],
            'totalInscripciones' => count(array_filter($inscripciones, fn($i) => $i->getEstadoInscripcion()->value !== 'cancelada')),
            'resumen' => array_values($resumen),
        ]);
    }

    /**
     * Get event registration persons report.
     */
    #[Route('/eventos/{id}/reporte-personas', name: 'api_admin_evento_reporte_personas', methods: ['GET'])]
    public function reportePersonas(int $id): JsonResponse
    {
        /** @var Usuario $admin */
        $admin = $this->getUser();
        $evento = $this->eventoRepository->find($id);

        if (!$evento) {
            return $this->json(['error' => 'Evento no encontrado'], 404);
        }

        if ($evento->getEntidad()->getId() !== $admin->getEntidad()->getId()) {
            return $this->json(['error' => 'Acceso denegado'], 403);
        }

        $inscripciones = $this->inscripcionRepository->findByEvento($evento);
        $personas = [];

        foreach ($inscripciones as $inscripcion) {
            if ($inscripcion->getEstadoInscripcion()->value === 'cancelada') {
                continue;
            }

            foreach ($inscripcion->getLineas() as $linea) {
                if ($linea->getEstadoLinea()->value === 'cancelada') {
                    continue;
                }

                $personas[] = [
                    'nombre' => $linea->getNombrePersonaSnapshot(),
                    'tipoPersona' => $linea->getTipoPersonaSnapshot(),
                    'actividad' => $linea->getNombreActividadSnapshot(),
                    'observaciones' => $linea->getObservaciones(),
                    'inscripcionCodigo' => $inscripcion->getCodigo(),
                    'inscriptor' => $inscripcion->getUsuario()->getNombreCompleto(),
                ];
            }
        }

        return $this->json([
            'evento' => [
                'id' => $evento->getId(),
                'titulo' => $evento->getTitulo(),
                'fecha' => $evento->getFechaEvento()->format('Y-m-d'),
            ],
            'totalPersonas' => count($personas),
            'personas' => $personas,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapUsuarioAdmin(Usuario $usuario): array
    {
        return [
            'id' => $usuario->getId(),
            'nombre' => $usuario->getNombre(),
            'apellidos' => $usuario->getApellidos(),
            'email' => $usuario->getEmail(),
            'telefono' => $usuario->getTelefono(),
            'tipoUsuarioEconomico' => $usuario->getTipoUsuarioEconomico()->value,
            'censadoVia' => $usuario->getCensadoVia()?->value,
            'activo' => $usuario->isActivo(),
            'fechaAltaCenso' => $usuario->getFechaAltaCenso()?->format('c'),
            'fechaBajaCenso' => $usuario->getFechaBajaCenso()?->format('c'),
        ];
    }
}
