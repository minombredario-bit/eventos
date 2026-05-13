<?php

namespace App\Controller;

use ApiPlatform\Metadata\IriConverterInterface;
use App\Entity\Evento;
use App\Entity\SeleccionParticipanteEvento;
use App\Entity\Usuario;
use App\Repository\EventoRepository;
use App\Repository\InvitadoRepository;
use App\Repository\InscripcionRepository;
use App\Repository\SeleccionParticipanteEventoRepository;
use App\Repository\UsuarioRepository;
use App\Service\InscripcionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class EventoController extends AbstractController
{
    public function __construct(
        private readonly EventoRepository $eventoRepository,
        private readonly InscripcionService $inscripcionService,
        private readonly InscripcionRepository $inscripcionRepository,
        private readonly InvitadoRepository $invitadoRepository,
        private readonly UsuarioRepository $usuarioRepository,
        private readonly SeleccionParticipanteEventoRepository $seleccionParticipanteEventoRepository,
        private readonly IriConverterInterface $iriConverter,
    ) {
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
        // Legacy payloads are no longer supported; only canonical keys are accepted.

        if (empty($data['persona']) || !is_array($data['persona'])) {
            return $this->json(['error' => 'Se requiere al menos una persona'], 400);
        }
        $personaData = $data['persona'];

        if (empty($personaData) || !is_array($personaData)) {
            return $this->json(['error' => 'Se requiere al menos una persona'], 400);
        }

        // Después
        $usuarioId = basename($personaData['usuario']);
        $user = $this->usuarioRepository->find($usuarioId);

        if ($user === null) {
            return $this->json(['error' => 'Usuario no encontrado'], 404);
        }

        try {
            $inscripcion = $this->inscripcionService->crearInscripcion(
                $evento,
                $user,
                [$personaData],   // el service hace foreach, necesita array de líneas
            );

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
                    'nombreActividadSnapshot' => $linea->getNombreActividadSnapshot(),
                    'actividadId' => $linea->getActividad()->getId(),
                    'precioUnitario' => $linea->getPrecioUnitario(),
                    'esDePagoSnapshot' => $linea->isEsDePagoSnapshot(),
                    'estadoLinea' => $linea->getEstadoLinea()->value,
                    'pagada' => $linea->isPagada(),
                ], $inscripcion->getLineas()->toArray()),
            ], 201);
        } catch (UnprocessableEntityHttpException $e) {
            return $this->json([
                'error' => $e->getMessage(),
                'code' => InscripcionService::ERROR_CODE_INSCRIPCION_CERRADA,
            ], 422);
        } catch (BadRequestHttpException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
