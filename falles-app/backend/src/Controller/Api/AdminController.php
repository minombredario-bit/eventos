<?php

namespace App\Controller\Api;

use App\Entity\Usuario;
use App\Entity\Inscripcion;
use App\Service\InscripcionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN_ENTIDAD')]
class AdminController extends AbstractController
{
    public function __construct(
        private InscripcionService $inscripcionService,
    ) {}

    /**
     * Lista de usuarios pendientes de validación en la entidad del admin.
     */
    #[Route('/usuarios-pendientes', name: 'admin_usuarios_pendientes', methods: ['GET'])]
    public function usuariosPendientes(#[CurrentUser] ?Usuario $user): JsonResponse
    {
        $entidad = $user->getEntidad();
        
        $usuarios = $entidad->getUsuarios()->filter(function (Usuario $u) {
            return $u->getEstadoValidacion()->value === 'pendiente_validacion';
        });

        $data = [];
        foreach ($usuarios as $usuario) {
            $data[] = [
                'id' => $usuario->getId(),
                'nombre' => $usuario->getNombre(),
                'apellidos' => $usuario->getApellidos(),
                'email' => $usuario->getEmail(),
                'telefono' => $usuario->getTelefono(),
                'fechaSolicitud' => $usuario->getFechaSolicitudAlta()?->format('Y-m-d H:i'),
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * Valida un usuario manualmente.
     */
    #[Route('/usuarios/{id}/validar', name: 'admin_usuario_validar', methods: ['POST'])]
    public function validarUsuario(string $id, #[CurrentUser] ?Usuario $admin): JsonResponse
    {
        $em = $this->container->get('doctrine')->getManager();
        $usuario = $em->getRepository(Usuario::class)->find($id);

        if (!$usuario) {
            return new JsonResponse(['error' => 'Usuario no encontrado'], 404);
        }

        // Verificar que el usuario pertenece a la entidad del admin
        if ($usuario->getEntidad()->getId() !== $admin->getEntidad()->getId()) {
            return new JsonResponse(['error' => 'No tienes permisos'], 403);
        }

        $usuario->setEstadoValidacion(\App\Enum\EstadoValidacionEnum::VALIDADO);
        $usuario->setValidadoPor($admin);
        $usuario->setFechaValidacion(new \DateTimeImmutable());
        
        $em->flush();

        return new JsonResponse(['message' => 'Usuario validado correctamente']);
    }

    /**
     * Rechaza un usuario.
     */
    #[Route('/usuarios/{id}/rechazar', name: 'admin_usuario_rechazar', methods: ['POST'])]
    public function rechazarUsuario(string $id, Request $request, #[CurrentUser] ?Usuario $admin): JsonResponse
    {
        $em = $this->container->get('doctrine')->getManager();
        $usuario = $em->getRepository(Usuario::class)->find($id);

        if (!$usuario) {
            return new JsonResponse(['error' => 'Usuario no encontrado'], 404);
        }

        if ($usuario->getEntidad()->getId() !== $admin->getEntidad()->getId()) {
            return new JsonResponse(['error' => 'No tienes permisos'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $motivo = $data['motivo'] ?? 'Rechazado por el administrador';

        $usuario->setEstadoValidacion(\App\Enum\EstadoValidacionEnum::RECHAZADO);
        $usuario->setValidadoPor($admin);
        $usuario->setFechaValidacion(new \DateTimeImmutable());
        
        $em->flush();

        return new JsonResponse(['message' => 'Usuario rechazado']);
    }

    /**
     * Lista de inscripciones de la entidad o evento.
     */
    #[Route('/inscripciones', name: 'admin_inscripciones', methods: ['GET'])]
    public function inscripciones(Request $request, #[CurrentUser] ?Usuario $user): JsonResponse
    {
        $em = $this->container->get('doctrine')->getManager();
        $eventoId = $request->query->get('evento_id');
        
        $qb = $em->createQueryBuilder()
            ->select('i')
            ->from(Inscripcion::class, 'i')
            ->join('i.entidad', 'e')
            ->join('i.usuario', 'u')
            ->where('e.id = :entidadId')
            ->setParameter('entidadId', $user->getEntidad()->getId())
            ->orderBy('i.createdAt', 'DESC');

        if ($eventoId) {
            $qb->andWhere('i.evento = :eventoId')
               ->setParameter('eventoId', $eventoId);
        }

        $inscripciones = $qb->getQuery()->getResult();

        $data = [];
        foreach ($inscripciones as $inscripcion) {
            $data[] = [
                'id' => $inscripcion->getId(),
                'codigo' => $inscripcion->getCodigo(),
                'usuario' => $inscripcion->getUsuario()->getNombre() . ' ' . $inscripcion->getUsuario()->getApellidos(),
                'evento' => $inscripcion->getEvento()->getTitulo(),
                'estado' => $inscripcion->getEstadoInscripcion()->value,
                'estadoPago' => $inscripcion->getEstadoPago()->value,
                'importeTotal' => $inscripcion->getImporteTotal(),
                'fecha' => $inscripcion->getCreatedAt()->format('Y-m-d H:i'),
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * Resumen de inscripciones por menú para un evento.
     */
    #[Route('/eventos/{id}/resumen-menu', name: 'admin_resumen_menu', methods: ['GET'])]
    public function resumenPorMenu(string $id, #[CurrentUser] ?Usuario $user): JsonResponse
    {
        $em = $this->container->get('doctrine')->getManager();
        $evento = $em->getRepository(\App\Entity\Evento::class)->find($id);

        if (!$evento || $evento->getEntidad()->getId() !== $user->getEntidad()->getId()) {
            return new JsonResponse(['error' => 'Evento no encontrado'], 404);
        }

        // Contar líneas por menú
        $lineas = $em->createQueryBuilder()
            ->select('m.nombre, COUNT(l.id) as cantidad, SUM(l.precioUnitario) as total')
            ->from(\App\Entity\InscripcionLinea::class, 'l')
            ->join('l.menu', 'm')
            ->join('l.inscripcion', 'i')
            ->where('i.evento = :evento')
            ->andWhere('i.estadoInscripcion != :cancelada')
            ->setParameter('evento', $evento)
            ->setParameter('cancelada', \App\Enum\EstadoInscripcionEnum::CANCELADA)
            ->groupBy('m.id')
            ->getQuery()
            ->getResult();

        $resumen = [
            'evento' => $evento->getTitulo(),
            'fecha' => $evento->getFechaEvento()->format('Y-m-d'),
            'menus' => [],
            'totales' => [
                'personas' => 0,
                'importe' => 0,
            ],
        ];

        foreach ($lineas as $linea) {
            $resumen['menus'][] = [
                'menu' => $linea['nombre'],
                'cantidad' => (int) $linea['cantidad'],
                'total' => (float) $linea['total'],
            ];
            $resumen['totales']['personas'] += (int) $linea['cantidad'];
            $resumen['totales']['importe'] += (float) $linea['total'];
        }

        return new JsonResponse($resumen);
    }

    /**
     * Crea una inscripción para un usuario en un evento.
     * 
     * Body JSON:
     * {
     *   "usuario_id": "uuid",
     *   "evento_id": "uuid",
     *   "lineas": [
     *     {
     *       "persona_id": "uuid",
     *       "menu_id": "uuid",
     *       "observaciones": "opcional"
     *     }
     *   ]
     * }
     */
    #[Route('/inscripciones', name: 'admin_crear_inscripcion', methods: ['POST'])]
    public function crearInscripcion(Request $request, #[CurrentUser] ?Usuario $admin): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return new JsonResponse(['error' => 'JSON inválido'], 400);
        }

        $usuarioId = $data['usuario_id'] ?? null;
        $eventoId = $data['evento_id'] ?? null;
        $lineasData = $data['lineas'] ?? [];

        if (!$usuarioId || !$eventoId || empty($lineasData)) {
            return new JsonResponse([
                'error' => 'Faltan datos requeridos',
                'required' => ['usuario_id', 'evento_id', 'lineas'],
            ], 400);
        }

        try {
            $inscripcion = $this->inscripcionService->crearInscripcion(
                $eventoId,
                $usuarioId,
                $lineasData
            );

            return new JsonResponse([
                'message' => 'Inscripción creada correctamente',
                'inscripcion' => [
                    'id' => $inscripcion->getId(),
                    'codigo' => $inscripcion->getCodigo(),
                    'usuario' => $inscripcion->getUsuario()->getNombre() . ' ' . $inscripcion->getUsuario()->getApellidos(),
                    'evento' => $inscripcion->getEvento()->getTitulo(),
                    'estado' => $inscripcion->getEstadoInscripcion()->value,
                    'estadoPago' => $inscripcion->getEstadoPago()->value,
                    'importeTotal' => $inscripcion->getImporteTotal(),
                    'lineas' => count($inscripcion->getLineas()),
                ],
            ], 201);

        } catch (BadRequestHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error al crear la inscripción: ' . $e->getMessage()], 500);
        }
    }
}
