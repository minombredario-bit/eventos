<?php

namespace App\Controller;

use App\Entity\Evento;
use App\Entity\Usuario;
use App\Repository\EventoRepository;
use App\Service\InscripcionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class EventoController extends AbstractController
{
    public function __construct(
        private readonly EventoRepository $eventoRepository,
        private readonly InscripcionService $inscripcionService
    ) {}

    /**
     * List published events for the user's entity.
     */
    #[Route('/eventos', name: 'api_eventos_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var Usuario $user */
        $user = $this->getUser();
        $eventos = $this->eventoRepository->findPublicadosByEntidad($user->getEntidad());

        $data = array_map(fn(Evento $evento) => [
            'id' => $evento->getId(),
            'titulo' => $evento->getTitulo(),
            'slug' => $evento->getSlug(),
            'descripcion' => $evento->getDescripcion(),
            'tipoEvento' => $evento->getTipoEvento()->value,
            'fechaEvento' => $evento->getFechaEvento()->format('Y-m-d'),
            'horaInicio' => $evento->getHoraInicio()?->format('H:i'),
            'horaFin' => $evento->getHoraFin()?->format('H:i'),
            'lugar' => $evento->getLugar(),
            'aforo' => $evento->getAforo(),
            'fechaInicioInscripcion' => $evento->getFechaInicioInscripcion()->format('c'),
            'fechaFinInscripcion' => $evento->getFechaFinInscripcion()->format('c'),
            'admitePago' => $evento->isAdmitePago(),
            'estado' => $evento->getEstado()->value,
            'inscripcionAbierta' => $evento->estaInscripcionAbierta(),
            'menus' => array_map(fn($menu) => [
                'id' => $menu->getId(),
                'nombre' => $menu->getNombre(),
                'tipoMenu' => $menu->getTipoMenu()->value,
                'franjaComida' => $menu->getFranjaComida()->value,
                'compatibilidadPersona' => $menu->getCompatibilidadPersona()->value,
                'esDePago' => $menu->isEsDePago(),
                'precioBase' => $menu->getPrecioBase(),
                'precioAdultoInterno' => $menu->getPrecioAdultoInterno(),
                'precioAdultoExterno' => $menu->getPrecioAdultoExterno(),
                'precioInfantil' => $menu->getPrecioInfantil(),
            ], $evento->getMenus()->toArray()),
        ], $eventos);

        return $this->json(['hydra:member' => $data]);
    }

    /**
     * Get event detail.
     */
    #[Route('/eventos/{id}', name: 'api_eventos_detail', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        $evento = $this->eventoRepository->find($id);

        if (!$evento) {
            return $this->json(['error' => 'Evento no encontrado'], 404);
        }

        return $this->json([
            'id' => $evento->getId(),
            'titulo' => $evento->getTitulo(),
            'slug' => $evento->getSlug(),
            'descripcion' => $evento->getDescripcion(),
            'tipoEvento' => $evento->getTipoEvento()->value,
            'fechaEvento' => $evento->getFechaEvento()->format('Y-m-d'),
            'horaInicio' => $evento->getHoraInicio()?->format('H:i'),
            'horaFin' => $evento->getHoraFin()?->format('H:i'),
            'lugar' => $evento->getLugar(),
            'aforo' => $evento->getAforo(),
            'fechaInicioInscripcion' => $evento->getFechaInicioInscripcion()->format('c'),
            'fechaFinInscripcion' => $evento->getFechaFinInscripcion()->format('c'),
            'visible' => $evento->isVisible(),
            'publicado' => $evento->isPublicado(),
            'admitePago' => $evento->isAdmitePago(),
            'estado' => $evento->getEstado()->value,
            'inscripcionAbierta' => $evento->estaInscripcionAbierta(),
            'menus' => array_map(fn($menu) => [
                'id' => $menu->getId(),
                'nombre' => $menu->getNombre(),
                'descripcion' => $menu->getDescripcion(),
                'tipoMenu' => $menu->getTipoMenu()->value,
                'franjaComida' => $menu->getFranjaComida()->value,
                'compatibilidadPersona' => $menu->getCompatibilidadPersona()->value,
                'esDePago' => $menu->isEsDePago(),
                'precioBase' => $menu->getPrecioBase(),
                'precioAdultoInterno' => $menu->getPrecioAdultoInterno(),
                'precioAdultoExterno' => $menu->getPrecioAdultoExterno(),
                'precioInfantil' => $menu->getPrecioInfantil(),
            ], $evento->getMenus()->toArray()),
        ]);
    }

    /**
     * Get menus for an event.
     */
    #[Route('/menu_eventos', name: 'api_menu_eventos_by_evento', methods: ['GET'])]
    public function menus(Request $request): JsonResponse
    {
        $eventoId = $request->query->get('evento');
        
        if (!$eventoId) {
            return $this->json(['error' => 'Parámetro requerido: evento'], 400);
        }

        $evento = $this->eventoRepository->find($eventoId);

        if (!$evento) {
            return $this->json(['error' => 'Evento no encontrado'], 404);
        }

        $menus = $evento->getMenus()->toArray();

        return $this->json(['hydra:member' => array_map(fn($menu) => [
            'id' => $menu->getId(),
            'nombre' => $menu->getNombre(),
            'descripcion' => $menu->getDescripcion(),
            'tipoMenu' => $menu->getTipoMenu()->value,
            'franjaComida' => $menu->getFranjaComida()->value,
            'compatibilidadPersona' => $menu->getCompatibilidadPersona()->value,
            'esDePago' => $menu->isEsDePago(),
            'precioBase' => $menu->getPrecioBase(),
            'precioAdultoInterno' => $menu->getPrecioAdultoInterno(),
            'precioAdultoExterno' => $menu->getPrecioAdultoExterno(),
            'precioInfantil' => $menu->getPrecioInfantil(),
            'activo' => $menu->isActivo(),
        ], $menus)]);
    }

    /**
     * Register for an event.
     */
    #[Route('/eventos/{id}/inscribirme', name: 'api_eventos_inscribirme', methods: ['POST'])]
    public function inscribirme(string $id, Request $request): JsonResponse
    {
        /** @var Usuario $user */
        $user = $this->getUser();

        $evento = $this->eventoRepository->find($id);

        if (!$evento) {
            return $this->json(['error' => 'Evento no encontrado'], 404);
        }

        // Verify user belongs to same entity
        if ($evento->getEntidad()->getId() !== $user->getEntidad()->getId()) {
            return $this->json(['error' => 'No tienes acceso a este evento'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['personas']) || !is_array($data['personas'])) {
            return $this->json(['error' => 'Se requiere al menos una persona'], 400);
        }

        try {
            $inscripcion = $this->inscripcionService->crearInscripcion($evento, $user, $data['personas']);

            return $this->json([
                'id' => $inscripcion->getId(),
                'codigo' => $inscripcion->getCodigo(),
                'estado' => $inscripcion->getEstadoInscripcion()->value,
                'estadoPago' => $inscripcion->getEstadoPago()->value,
                'importeTotal' => $inscripcion->getImporteTotal(),
                'importePagado' => $inscripcion->getImportePagado(),
                'lineas' => array_map(fn($linea) => [
                    'id' => $linea->getId(),
                    'nombrePersonaSnapshot' => $linea->getNombrePersonaSnapshot(),
                    'tipoPersonaSnapshot' => $linea->getTipoPersonaSnapshot(),
                    'franjaComidaSnapshot' => $linea->getFranjaComidaSnapshot(),
                    'nombreMenuSnapshot' => $linea->getNombreMenuSnapshot(),
                    'precioUnitario' => $linea->getPrecioUnitario(),
                    'esDePagoSnapshot' => $linea->isEsDePagoSnapshot(),
                    'estadoLinea' => $linea->getEstadoLinea()->value,
                ], $inscripcion->getLineas()->toArray()),
            ], 201);
        } catch (BadRequestHttpException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
