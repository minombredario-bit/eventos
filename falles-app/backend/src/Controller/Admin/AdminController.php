<?php

namespace App\Controller\Admin;

use App\Entity\CensoEntrada;
use App\Entity\Entidad;
use App\Entity\Evento;
use App\Entity\Inscripcion;
use App\Entity\Usuario;
use App\Enum\CensadoViaEnum;
use App\Enum\EstadoValidacionEnum;
use App\Enum\TipoRelacionEconomicaEnum;
use App\Repository\CensoEntradaRepository;
use App\Repository\EntidadRepository;
use App\Repository\EventoRepository;
use App\Repository\InscripcionRepository;
use App\Repository\UsuarioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly UsuarioRepository $usuarioRepository,
        private readonly EventoRepository $eventoRepository,
        private readonly InscripcionRepository $inscripcionRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

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
            'fechaSolicitudAlta' => $user->getFechaSolicitudAlta()?->format('c'),
            'codigoRegistroUsado' => $user->getCodigoRegistroUsado(),
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

        $user->setEstadoValidacion(EstadoValidacionEnum::VALIDADO);
        $user->setValidadoPor($admin);
        $user->setFechaValidacion(new \DateTimeImmutable());

        if (isset($data['tipoUsuarioEconomico'])) {
            $user->setTipoUsuarioEconomico(TipoRelacionEconomicaEnum::from($data['tipoUsuarioEconomico']));
        }

        if (isset($data['censadoVia'])) {
            $user->setCensadoVia(CensadoViaEnum::from($data['censadoVia']));
        }

        $this->entityManager->flush();

        return $this->json([
            'id' => $user->getId(),
            'estadoValidacion' => $user->getEstadoValidacion()->value,
            'validadoPor' => $admin->getEmail(),
            'fechaValidacion' => $user->getFechaValidacion()?->format('c'),
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

        $user->setEstadoValidacion(EstadoValidacionEnum::RECHAZADO);
        $this->entityManager->flush();

        return $this->json([
            'id' => $user->getId(),
            'estadoValidacion' => $user->getEstadoValidacion()->value,
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

        $user->setEsCensadoInterno(true);
        $user->setFechaAltaCenso(new \DateTimeImmutable());
        $user->setTipoUsuarioEconomico(TipoRelacionEconomicaEnum::INTERNO);
        $this->entityManager->flush();

        return $this->json([
            'id' => $user->getId(),
            'esCensadoInterno' => $user->isEsCensadoInterno(),
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

        $user->setEsCensadoInterno(false);
        $user->setFechaBajaCenso(new \DateTimeImmutable());
        $user->setMotivoBajaCenso($data['motivo'] ?? null);
        $user->setTipoUsuarioEconomico(TipoRelacionEconomicaEnum::EXTERNO);
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

        // Build summary by menu
        $resumen = [];
        foreach ($evento->getMenus() as $menu) {
            $resumen[$menu->getNombre()] = [
                'menu' => $menu->getNombre(),
                'tipo' => $menu->getTipoMenu()->value,
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

                $menuNombre = $linea->getNombreMenuSnapshot();
                if (isset($resumen[$menuNombre])) {
                    $tipoPersona = $linea->getTipoPersonaSnapshot();
                    if ($tipoPersona === 'infantil') {
                        $resumen[$menuNombre]['infantiles']++;
                    } else {
                        $resumen[$menuNombre]['adultos']++;
                    }
                    $resumen[$menuNombre]['total']++;
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
                    'menu' => $linea->getNombreMenuSnapshot(),
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
}
