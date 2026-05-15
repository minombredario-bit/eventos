<?php

namespace App\Controller\Admin;

use App\Entity\Evento;
use App\Entity\Inscripcion;
use App\Entity\Pago;
use App\Entity\Usuario;
use App\Enum\CensadoViaEnum;
use App\Enum\EstadoInscripcionEnum;
use App\Enum\EstadoPagoEnum;
use App\Enum\EstadoValidacionEnum;
use App\Enum\MetodoPagoEnum;
use App\Enum\TipoRelacionEconomicaEnum;
use App\Enum\TipoRelacionEnum;
use App\Repository\EventoRepository;
use App\Repository\InscripcionLineaRepository;
use App\Repository\InscripcionRepository;
use App\Repository\PagoRepository;
use App\Repository\UsuarioRepository;
use App\Service\CensoImporterService;
use App\Service\EmailQueueService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

#[Route('/api/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly UsuarioRepository $usuarioRepository,
        private readonly EventoRepository $eventoRepository,
        private readonly InscripcionRepository $inscripcionRepository,
        private readonly InscripcionLineaRepository $inscripcionLineaRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly CensoImporterService $censoImporter,
        private readonly EmailQueueService $emailQueueService,
        private readonly string $defaultUri,
        private readonly PagoRepository $pagoRepository,
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
        $this->emailQueueService->enqueueUserWelcome($usuario, (string) $data['password'], $this->defaultUri);
        $this->entityManager->flush();

        return $this->json([
            'id' => $usuario->getId(),
            'email' => $usuario->getEmail(),
            'activo' => $usuario->isActivo(),
        ], 201);
    }

    #[Route('/usuarios/importar-excel', name: 'api_admin_usuarios_importar_excel', methods: ['POST'])]
    public function importarUsuariosExcel(Request $request): Response
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

        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('usuarios_', true) . '.' . $extension;
        $file->move(dirname($tempPath), basename($tempPath));

        try {
            $resultado = $this->censoImporter->importar(
                $tempPath,
                $admin->getEntidad(),
                $admin->getEntidad()->getTemporadaActual()
            );

            @unlink($tempPath);

            if (!empty($resultado['passwords_excel']) && file_exists($resultado['passwords_excel'])) {
                $response = new BinaryFileResponse($resultado['passwords_excel']);

                $response->headers->set(
                    'Content-Type',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                );

                $response->setContentDisposition(
                    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    'usuarios_passwords_' . date('Ymd_His') . '.xlsx'
                );

                $response->headers->set('X-Import-Total', (string) $resultado['total']);
                $response->headers->set('X-Import-Insertadas', (string) $resultado['insertadas']);
                $response->headers->set('X-Import-Actualizadas', (string) $resultado['actualizadas']);
                $response->headers->set('X-Import-Relaciones', (string) ($resultado['relaciones'] ?? 0));
                $response->headers->set('X-Import-Errores-Count', (string) count($resultado['errores']));
                $response->headers->set(
                    'X-Import-Errores',
                    base64_encode(json_encode($resultado['errores'], JSON_UNESCAPED_UNICODE))
                );

                $response->deleteFileAfterSend(true);

                return $response;
            }

            return $this->json($resultado);
        } catch (\Throwable $e) {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }

            return $this->json(['error' => 'Error procesando el archivo: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/usuarios-exportar-excel', name: 'api_admin_usuarios_exportar_excel', methods: ['GET'])]
    public function exportarUsuariosExcel(): Response
    {
        /** @var Usuario $admin */
        $admin = $this->getUser();

        $usuarios = $this->usuarioRepository->findBy(
            ['entidad' => $admin->getEntidad()],
            ['nombre' => 'ASC', 'apellidos' => 'ASC']
        );

        $gruposFamiliares = $this->censoImporter->generarCodigosGrupo($usuarios, TipoRelacionEnum::FAMILIAR, 'fam');
        $gruposAmistad = $this->censoImporter->generarCodigosGrupo($usuarios, TipoRelacionEnum::AMISTAD, 'ami');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Usuarios');

        $sheet->fromArray([
            'codigo_usuario',
            'Nombre',
            'Apellidos',
            'direccion',
            'dni',
            'movil',
            'fecha_nacimiento',
            'email',
            'debe_cambiar_password',
            'fecha_baja_censo',
            'motivo_baja_censo',
            'antiguedad',
            'grupo_familiar',
            'grupo_amistad',
        ], null, 'A1');

        $row = 2;

        foreach ($usuarios as $usuario) {
            $userId = (string) $usuario->getId();

            $sheet->fromArray([
                $userId,
                $usuario->getNombre(),
                $usuario->getApellidos(),
                $usuario->getDireccion(),
                $usuario->getDocumentoIdentidad(),
                $usuario->getTelefono(),
                $usuario->getFechaNacimiento()?->format('d/m/Y'),
                $usuario->getEmail(),
                $usuario->isDebeCambiarPassword() ? 1 : 0,
                $usuario->getFechaBajaCenso()?->format('d/m/Y'),
                $usuario->getMotivoBajaCenso(),
                $usuario->getAntiguedad(),
                $gruposFamiliares[$userId] ?? '',
                $gruposAmistad[$userId] ?? '',
            ], null, 'A' . $row);

            $row++;
        }

        foreach (range('A', 'N') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $fileName = 'usuarios_entidad_' . date('Ymd_His') . '.xlsx';
        $filePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $fileName;

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $response = new BinaryFileResponse($filePath);

        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $fileName
        );

        $response->deleteFileAfterSend(true);

        return $response;
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
     * Get a single inscription with lines and payment history (admin).
     */
    #[Route('/inscripciones/{id}', name: 'api_admin_inscripcion_detail', methods: ['GET'])]
    public function inscripcionDetalle(string $id): JsonResponse
    {
        /** @var Usuario $admin */
        $admin = $this->getUser();
        $inscripcion = $this->inscripcionRepository->find($id);

        if (!$inscripcion) {
            return $this->json(['error' => 'Inscripción no encontrada'], 404);
        }

        if ($inscripcion->getEntidad()->getId() !== $admin->getEntidad()->getId()) {
            return $this->json(['error' => 'Acceso denegado'], 403);
        }

        $lineas = array_values(array_map(fn($linea) => [
            'id'                           => $linea->getId(),
            'nombrePersonaSnapshot'        => $linea->getNombrePersonaSnapshot(),
            'tipoPersonaSnapshot'          => $linea->getTipoPersonaSnapshot(),
            'tipoRelacionEconomicaSnapshot' => $linea->getTipoRelacionEconomicaSnapshot(),
            'estadoValidacionSnapshot'     => $linea->getEstadoValidacionSnapshot(),
            'nombreActividadSnapshot'      => $linea->getNombreActividadSnapshot(),
            'franjaComidaSnapshot'         => $linea->getFranjaComidaSnapshot(),
            'esDePagoSnapshot'             => $linea->isEsDePagoSnapshot(),
            'precioUnitario'               => $linea->getPrecioUnitario(),
            'estadoLinea'                  => $linea->getEstadoLinea()->value,
            'pagada'                       => $linea->isPagada(),
            'observaciones'               => $linea->getObservaciones(),
        ], $inscripcion->getLineas()->toArray()));

        $pagos = array_values(array_map(fn(Pago $pago) => [
            'id'           => $pago->getId(),
            'fecha'        => $pago->getFecha()->format('c'),
            'importe'      => $pago->getImporte(),
            'metodoPago'   => $pago->getMetodoPago()->value,
            'referencia'   => $pago->getReferencia(),
            'estado'       => $pago->getEstado(),
            'observaciones' => $pago->getObservaciones(),
            'registradoPor' => $pago->getRegistradoPor()->getNombreCompleto(),
        ], $inscripcion->getPagos()->toArray()));

        return $this->json([
            'id'                => $inscripcion->getId(),
            'codigo'            => $inscripcion->getCodigo(),
            'usuario'           => [
                'id'       => $inscripcion->getUsuario()->getId(),
                'nombre'   => $inscripcion->getUsuario()->getNombre(),
                'apellidos' => $inscripcion->getUsuario()->getApellidos(),
                'email'    => $inscripcion->getUsuario()->getEmail(),
            ],
            'evento'            => [
                'id'     => $inscripcion->getEvento()->getId(),
                'titulo' => $inscripcion->getEvento()->getTitulo(),
                'fecha'  => $inscripcion->getEvento()->getFechaEvento()->format('Y-m-d'),
            ],
            'estadoInscripcion' => $inscripcion->getEstadoInscripcion()->value,
            'estadoPago'        => $inscripcion->getEstadoPago()->value,
            'importeTotal'      => $inscripcion->calcularImporteTotal(),
            'importePagado'     => $inscripcion->getImportePagado(),
            'observaciones'     => $inscripcion->getObservaciones(),
            'createdAt'         => $inscripcion->getCreatedAt()->format('c'),
            'updatedAt'         => $inscripcion->getUpdatedAt()->format('c'),
            'lineas'            => $lineas,
            'pagos'             => $pagos,
        ]);
    }

    /**
     * Register a manual payment for an inscription (admin).
     */
    #[Route('/inscripciones/{id}/registrar_pago', name: 'api_admin_inscripcion_registrar_pago', methods: ['POST'])]
    public function registrarPago(string $id, Request $request): JsonResponse
    {
        /** @var Usuario $admin */
        $admin = $this->getUser();
        $inscripcion = $this->inscripcionRepository->find($id);

        if (!$inscripcion) {
            return $this->json(['error' => 'Inscripción no encontrada'], 404);
        }

        if ($inscripcion->getEntidad()->getId() !== $admin->getEntidad()->getId()) {
            return $this->json(['error' => 'Acceso denegado'], 403);
        }

        $pendiente = round($inscripcion->calcularImporteTotal() - $inscripcion->getImportePagado(), 2);

        if ($pendiente <= 0.0) {
            return $this->json(['error' => 'La inscripción no tiene importe pendiente de pago'], 400);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['metodoPago'])) {
            return $this->json(['error' => 'El campo metodoPago es obligatorio'], 400);
        }

        $metodoPago = MetodoPagoEnum::tryFrom((string) $data['metodoPago']);
        if ($metodoPago === null) {
            return $this->json(['error' => 'Método de pago no válido'], 400);
        }

        $pago = new Pago();
        $pago->setInscripcion($inscripcion);
        $pago->setImporte($pendiente);
        $pago->setMetodoPago($metodoPago);
        $pago->setReferencia($data['referencia'] ?? null);
        $pago->setObservaciones($data['observaciones'] ?? null);
        $pago->setRegistradoPor($admin);
        $pago->setEstado('confirmado');

        $this->entityManager->persist($pago);

        // Actualizar importe pagado y estado de pago en la inscripción
        $importePagadoActual = round($inscripcion->getImportePagado() + $pendiente, 2);
        $importeTotal = round($inscripcion->calcularImporteTotal(), 2);
        $inscripcion->setImportePagado(min($importePagadoActual, $importeTotal));

        // Marcar líneas activas como pagadas
        foreach ($inscripcion->getLineas() as $linea) {
            if ($linea->getEstadoLinea()->value !== 'cancelada') {
                $linea->setPagada(true);
            }
        }

        $inscripcion->actualizarEstadoPago();
        // Si el pago cubre el total → confirmar la inscripción
        if ($inscripcion->getEstadoPago() === EstadoPagoEnum::PAGADO) {
            $inscripcion->setEstadoInscripcion(EstadoInscripcionEnum::CONFIRMADA);
        }

        $this->entityManager->flush();
        $this->emailQueueService->enqueueInscripcionCambio($inscripcion, 'pago');

        return $this->json([
            'pagoId'            => $pago->getId(),
            'importe'           => $pago->getImporte(),
            'metodoPago'        => $pago->getMetodoPago()->value,
            'estadoPago'        => $inscripcion->getEstadoPago()->value,
            'estadoInscripcion' => $inscripcion->getEstadoInscripcion()->value,
            'importeTotal'      => $inscripcion->calcularImporteTotal(),
            'importePagado'     => $inscripcion->getImportePagado(),
        ], 201);
    }

    /**
     * List payments for the admin's entity.
     */
    #[Route('/pagos', name: 'api_admin_pagos_list', methods: ['GET'])]
    public function pagos(Request $request): JsonResponse
    {
        /** @var Usuario $admin */
        $admin = $this->getUser();
        $eventoId = $request->query->get('evento');

        $qb = $this->entityManager->createQueryBuilder()
            ->select('p', 'i', 'u', 'e')
            ->from(Pago::class, 'p')
            ->join('p.inscripcion', 'i')
            ->join('i.usuario', 'u')
            ->join('i.evento', 'e')
            ->where('i.entidad = :entidad')
            ->setParameter('entidad', $admin->getEntidad())
            ->orderBy('p.fecha', 'DESC');

        if ($eventoId) {
            $qb->andWhere('e.id = :eventoId')->setParameter('eventoId', $eventoId);
        }

        /** @var Pago[] $pagos */
        $pagos = $qb->getQuery()->getResult();

        $data = array_map(fn(Pago $pago) => [
            'id'            => $pago->getId(),
            'fecha'         => $pago->getFecha()->format('c'),
            'importe'       => $pago->getImporte(),
            'metodoPago'    => $pago->getMetodoPago()->value,
            'referencia'    => $pago->getReferencia(),
            'estado'        => $pago->getEstado(),
            'observaciones' => $pago->getObservaciones(),
            'inscripcion'   => [
                'id'     => $pago->getInscripcion()->getId(),
                'codigo' => $pago->getInscripcion()->getCodigo(),
            ],
            'usuario'       => [
                'id'       => $pago->getInscripcion()->getUsuario()->getId(),
                'nombre'   => $pago->getInscripcion()->getUsuario()->getNombre(),
                'apellidos' => $pago->getInscripcion()->getUsuario()->getApellidos(),
            ],
            'evento'        => [
                'id'     => $pago->getInscripcion()->getEvento()->getId(),
                'titulo' => $pago->getInscripcion()->getEvento()->getTitulo(),
            ],
            'registradoPor' => $pago->getRegistradoPor()->getNombreCompleto(),
        ], $pagos);

        return $this->json(['hydra:member' => $data, 'hydra:totalItems' => count($data)]);
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
    public function reportePersonas(string $id): JsonResponse
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

    // ─────────────────────────────────────────────────────────────────────────
    // Dashboard stats
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/dashboard-stats', name: 'api_admin_dashboard_stats', methods: ['GET'])]
    public function dashboardStats(): JsonResponse
    {
        /** @var Usuario $admin */
        $admin = $this->getUser();
        $entidad = $admin->getEntidad();
        $today = new \DateTimeImmutable('today');

        $em = $this->entityManager;

        // ── 1. Total asistencia histórica (lineas confirmadas en eventos pasados) ─────
        $totalLineas = (int) $em->createQuery(
            'SELECT COUNT(l.id)
             FROM App\Entity\InscripcionLinea l
             JOIN l.inscripcion i
             JOIN i.evento e
             WHERE e.entidad = :entidad
               AND e.fechaEvento < :today
               AND l.estadoLinea != :cancelada'
        )
        ->setParameter('entidad', $entidad)
        ->setParameter('today', $today)
        ->setParameter('cancelada', 'cancelada')
        ->getSingleScalarResult();

        // ── 2. Total eventos pasados ─────────────────────────────────────────────────
        $totalEventosPasados = (int) $em->createQuery(
            'SELECT COUNT(e.id)
             FROM App\Entity\Evento e
             WHERE e.entidad = :entidad
               AND e.fechaEvento < :today
               AND e.estado != :cancelado'
        )
        ->setParameter('entidad', $entidad)
        ->setParameter('today', $today)
        ->setParameter('cancelado', 'cancelado')
        ->getSingleScalarResult();

        // ── 3. Media por franja horaria ──────────────────────────────────────────────
        //    Para cada franja, calculamos el total de asistentes y el nº de eventos que
        //    incluyeron esa franja, y derivamos la media.
        $franjaRaw = $em->createQuery(
            'SELECT l.franjaComidaSnapshot AS franja,
                    COUNT(l.id) AS totalAsistentes,
                    COUNT(DISTINCT e.id) AS numEventos
             FROM App\Entity\InscripcionLinea l
             JOIN l.inscripcion i
             JOIN i.evento e
             WHERE e.entidad = :entidad
               AND e.fechaEvento < :today
               AND l.estadoLinea != :cancelada
             GROUP BY l.franjaComidaSnapshot'
        )
        ->setParameter('entidad', $entidad)
        ->setParameter('today', $today)
        ->setParameter('cancelada', 'cancelada')
        ->getResult();

        $mediaPorFranja = [];
        foreach ($franjaRaw as $row) {
            $franjaRaw2 = $row['franja'];
            // franjaComidaSnapshot es string, pero apply enum-safe cast por precaución
            $franja = $franjaRaw2 instanceof \BackedEnum
                ? $franjaRaw2->value
                : (string) ($franjaRaw2 ?? '');
            if ($franja === '') {
                continue;
            }
            $numEventos = (int) $row['numEventos'];
            $mediaPorFranja[$franja] = $numEventos > 0
                ? round((int) $row['totalAsistentes'] / $numEventos, 1)
                : 0.0;
        }

        // ── 4. Media por tipo de actividad (compatibilidadPersona) ──────────────────
        //    Clasificamos cada evento pasado según la compatibilidad de sus actividades:
        //      - "adulto"  → todas las actividades son adulto/cadete
        //      - "infantil"→ todas las actividades son infantil
        //      - "ambos"   → hay mezcla o alguna actividad con AMBOS
        $eventosTipoRaw = $em->createQuery(
            'SELECT e.id AS eventoId,
                    a.compatibilidadPersona AS compat,
                    COUNT(l.id) AS asistentes
             FROM App\Entity\Evento e
             JOIN e.actividades a
             LEFT JOIN App\Entity\InscripcionLinea l
                 WITH l.actividad = a
                 AND l.estadoLinea != :cancelada
             WHERE e.entidad = :entidad
               AND e.fechaEvento < :today
               AND e.estado != :cancelado
             GROUP BY e.id, a.compatibilidadPersona'
        )
        ->setParameter('entidad', $entidad)
        ->setParameter('today', $today)
        ->setParameter('cancelada', 'cancelada')
        ->setParameter('cancelado', 'cancelado')
        ->getResult();

        // Agrupa por evento → map eventoId → [compat → totalAsistentes]
        $eventoCompatMap = [];
        foreach ($eventosTipoRaw as $row) {
            // UUID puede llegar como objeto en Doctrine → forzar string
            $eventoId = (string) $row['eventoId'];

            // El campo enum puede llegar como objeto BackedEnum o como string
            $compatRaw = $row['compat'];
            if ($compatRaw === null) {
                continue; // actividad sin compatibilidad definida → ignorar
            }
            $compat = $compatRaw instanceof \BackedEnum
                ? $compatRaw->value
                : (string) $compatRaw;

            if (!isset($eventoCompatMap[$eventoId])) {
                $eventoCompatMap[$eventoId] = [];
            }
            $eventoCompatMap[$eventoId][$compat] = ((int) ($eventoCompatMap[$eventoId][$compat] ?? 0)) + (int) $row['asistentes'];
        }

        // Clasifica el evento y acumula
        $tipoGroups = ['adulto' => ['total' => 0, 'eventos' => 0], 'infantil' => ['total' => 0, 'eventos' => 0], 'ambos' => ['total' => 0, 'eventos' => 0]];
        foreach ($eventoCompatMap as $compats) {
            $compatKeys = array_keys($compats);
            $totalEvento = array_sum($compats);

            $hasInfantil = in_array('infantil', $compatKeys, true);
            $hasAdulto   = !empty(array_intersect($compatKeys, ['adulto', 'cadete']));
            $hasAmbos    = in_array('ambos', $compatKeys, true);

            if ($hasAmbos || ($hasInfantil && $hasAdulto)) {
                $grupo = 'ambos';
            } elseif ($hasInfantil) {
                $grupo = 'infantil';
            } else {
                $grupo = 'adulto';
            }

            $tipoGroups[$grupo]['total'] += $totalEvento;
            $tipoGroups[$grupo]['eventos']++;
        }

        $mediaPorTipo = [];
        foreach ($tipoGroups as $tipo => $data) {
            $mediaPorTipo[$tipo] = $data['eventos'] > 0
                ? round($data['total'] / $data['eventos'], 1)
                : null;
        }

        return $this->json([
            'totalAsistenciaHistorica' => $totalLineas,
            'totalEventosPasados'      => $totalEventosPasados,
            'mediaAsistenciaGeneral'   => $totalEventosPasados > 0 ? round($totalLineas / $totalEventosPasados, 1) : 0.0,
            'mediaPorFranja'           => $mediaPorFranja,
            'mediaPorTipo'             => $mediaPorTipo,
        ]);
    }
}
