<?php

namespace App\Controller;

use App\Entity\Evento;
use App\Entity\SeleccionParticipantesEvento;
use App\Entity\Usuario;
use App\Repository\EventoRepository;
use App\Repository\SeleccionParticipantesEventoRepository;
use App\Service\InscripcionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class EventoController extends AbstractController
{
    /**
     * List published events for the user's entity.
     */
    #[Route('/eventos', name: 'api_eventos_list', methods: ['GET'])]
    public function list(EventoRepository $eventoRepository): JsonResponse
    {
        /** @var Usuario $user */
        $user = $this->getUser();
        $eventos = $eventoRepository->findPublicadosByEntidad($user->getEntidad());

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
            'inscripcionAbierta' => $evento->getInscripcionAbierta(),
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
    #[Route('/eventos/{id}', name: 'api_eventos_detail', methods: ['GET'], priority: 100)]
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
            'inscripcionAbierta' => $evento->getInscripcionAbierta(),
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

            $seleccionParticipantesEventoRepository = $this->getSeleccionParticipantesEventoRepository();
            $entityManager = $this->getEntityManager();

            $seleccionExistente = $seleccionParticipantesEventoRepository->findOneByUsuarioAndEvento($user, $evento);
            if ($seleccionExistente !== null) {
                $entityManager->remove($seleccionExistente);
                $entityManager->flush();
            }

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

    #[Route('/eventos/{id}/seleccion_participantes', name: 'api_eventos_get_seleccion_participantes', methods: ['GET'])]
    public function getSeleccionParticipantes(string $id): JsonResponse
    {
        /** @var Usuario $user */
        $user = $this->getUser();

        $evento = $this->eventoRepository->find($id);
        if (!$evento) {
            return $this->json(['error' => 'Evento no encontrado'], 404);
        }

        if ($evento->getEntidad()->getId() !== $user->getEntidad()->getId()) {
            return $this->json(['error' => 'No tienes acceso a este evento'], 403);
        }

        $seleccion = $this->getSeleccionParticipantesEventoRepository()->findOneByUsuarioAndEvento($user, $evento);

        if ($seleccion === null) {
            return $this->json([
                'eventoId' => $evento->getId(),
                'participantes' => [],
                'updatedAt' => null,
            ]);
        }

        return $this->json([
            'eventoId' => $evento->getId(),
            'participantes' => $seleccion->getParticipantes(),
            'updatedAt' => $seleccion->getUpdatedAt()->format('c'),
        ]);
    }

    #[Route('/eventos/{id}/seleccion_participantes', name: 'api_eventos_put_seleccion_participantes', methods: ['PUT'])]
    public function putSeleccionParticipantes(string $id, Request $request): JsonResponse
    {
        /** @var Usuario $user */
        $user = $this->getUser();

        $evento = $this->eventoRepository->find($id);
        if (!$evento) {
            return $this->json(['error' => 'Evento no encontrado'], 404);
        }

        if ($evento->getEntidad()->getId() !== $user->getEntidad()->getId()) {
            return $this->json(['error' => 'No tienes acceso a este evento'], 403);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'JSON inválido'], 400);
        }

        if (!array_key_exists('participantes', $payload) || !is_array($payload['participantes'])) {
            return $this->json(['error' => 'El campo participantes es obligatorio y debe ser un array'], 400);
        }

        $participantes = [];
        foreach ($payload['participantes'] as $index => $participante) {
            if (!is_array($participante)) {
                return $this->json(['error' => sprintf('Participante inválido en índice %d', $index)], 400);
            }

            $origen = $participante['origen'] ?? null;
            $participanteId = $participante['id'] ?? null;

            if (!is_string($origen) || !in_array($origen, ['familiar', 'no_fallero'], true)) {
                return $this->json(['error' => sprintf('Origen inválido en índice %d', $index)], 400);
            }

            if (!is_string($participanteId) || trim($participanteId) === '') {
                return $this->json(['error' => sprintf('ID de participante inválido en índice %d', $index)], 400);
            }

            $participantes[] = $participante;
        }

        $seleccionParticipantesEventoRepository = $this->getSeleccionParticipantesEventoRepository();
        $entityManager = $this->getEntityManager();

        $seleccion = $seleccionParticipantesEventoRepository->findOneByUsuarioAndEvento($user, $evento);

        if ($seleccion === null) {
            $seleccion = new SeleccionParticipantesEvento();
            $seleccion->setUsuario($user);
            $seleccion->setEvento($evento);
            $entityManager->persist($seleccion);
        }

        $seleccion->setParticipantes($participantes);
        $entityManager->flush();

        return $this->json([
            'eventoId' => $evento->getId(),
            'participantes' => $seleccion->getParticipantes(),
            'updatedAt' => $seleccion->getUpdatedAt()->format('c'),
        ]);
    }

    #[Route('/eventos/{id}/seleccion_participantes', name: 'api_eventos_delete_seleccion_participantes', methods: ['DELETE'])]
    public function deleteSeleccionParticipantes(string $id): JsonResponse
    {
        /** @var Usuario $user */
        $user = $this->getUser();

        $evento = $this->eventoRepository->find($id);
        if (!$evento) {
            return $this->json(['error' => 'Evento no encontrado'], 404);
        }

        if ($evento->getEntidad()->getId() !== $user->getEntidad()->getId()) {
            return $this->json(['error' => 'No tienes acceso a este evento'], 403);
        }

        $seleccion = $this->getSeleccionParticipantesEventoRepository()->findOneByUsuarioAndEvento($user, $evento);

        if ($seleccion !== null) {
            $entityManager = $this->getEntityManager();
            $entityManager->remove($seleccion);
            $entityManager->flush();
        }

        return $this->json([], 204);
    }
}
