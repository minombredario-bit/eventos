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
     * Get activities for an event (legacy endpoint name preserved).
     */
//    #[Route('/actividad_eventos', name: 'api_actividad_eventos_by_evento', methods: ['GET'])]
    public function actividades(Request $request): JsonResponse
    {
        $eventoId = $request->query->get('evento');

        if (!$eventoId) {
            return $this->json(['error' => 'Parámetro requerido: evento'], 400);
        }

        $evento = $this->eventoRepository->find($eventoId);

        if (!$evento) {
            return $this->json(['error' => 'Evento no encontrado'], 404);
        }

        $actividades = $evento->getActividades()->toArray();

        $response = $this->json(['hydra:member' => array_map(fn($actividad) => [
            'id' => $actividad->getId(),
            'nombre' => $actividad->getNombre(),
            'descripcion' => $actividad->getDescripcion(),
            'tipoActividad' => $actividad->getTipoActividad()->value,
            'franjaComida' => $actividad->getFranjaComida()->value,
            'compatibilidadPersona' => $actividad->getCompatibilidadPersona()->value,
            'esDePago' => $actividad->isEsDePago(),
            'precioBase' => $actividad->getPrecioBase(),
            'precioAdultoInterno' => $actividad->getPrecioAdultoInterno(),
            'precioAdultoExterno' => $actividad->getPrecioAdultoExterno(),
            'precioInfantil' => $actividad->getPrecioInfantil(),
            'activo' => $actividad->isActivo(),
        ], $actividades)]);

        // FIX: eliminados headers X-Debug-Route y X-Debug-Path — no deben estar en producción

        return $response;
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

        $user = $this->iriConverter->getResourceFromIri($personaData['usuario']);

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

    public function getInvitados(string $id): JsonResponse
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

        $invitados = $this->invitadoRepository->findByEventoAndUsuario($evento, $user);

        return $this->json([
            'hydra:member' => array_map(fn($invitado) => $this->mapInvitadoForEventoResponse($invitado), $invitados),
            'hydra:totalItems' => count($invitados),
        ]);
    }

    public function getParticipantesExternos(string $id): JsonResponse
    {
        return $this->getInvitados($id);
    }

//    #[Route('/eventos/{id}/apuntados', name: 'api_eventos_apuntados', methods: ['GET'])]
    public function getApuntados(string $id, Request $request): JsonResponse
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

        $search = $request->query->get('q');
        $paginateParam = $request->query->get('paginate', 'true');
        $paginate = !is_string($paginateParam)
            || !in_array(mb_strtolower(trim($paginateParam)), ['0', 'false', 'no', 'off'], true);
        $page = max(1, (int) $request->query->get('page', '1'));
        $itemsPerPage = 10;

        $member = $this->buildApuntadosForEvento($evento, $user, is_string($search) ? $search : null);

        $totalItems = count($member);
        $lastPage = max(1, (int) ceil($totalItems / $itemsPerPage));
        $currentPage = min($page, $lastPage);

        $paginatedMember = $member;
        if ($paginate) {
            $offset = ($currentPage - 1) * $itemsPerPage;
            $paginatedMember = array_slice($member, $offset, $itemsPerPage);
        }

        return $this->json([
            'evento' => [
                'id' => $evento->getId(),
                'titulo' => $evento->getTitulo(),
                'fechaEvento' => $evento->getFechaEvento()->format('Y-m-d'),
            ],
            'hydra:member' => $paginatedMember,
            'hydra:totalItems' => $totalItems,
            'hydra:itemsPerPage' => $paginate ? $itemsPerPage : $totalItems,
            'hydra:currentPage' => $paginate ? $currentPage : 1,
            'hydra:lastPage' => $paginate ? $lastPage : 1,
        ]);
    }

    private static function buildNombreCompleto(?string $nombre, ?string $apellido): string
    {
        $partes = array_filter([
            is_string($nombre) ? trim($nombre) : '',
            is_string($apellido) ? trim($apellido) : '',
        ], static fn (string $parte): bool => $parte !== '');

        $nombreCompleto = trim(implode(' ', $partes));

        return preg_replace('/\s+/', ' ', $nombreCompleto) ?? '';
    }

    /**
     * @return list<array{inscripcionId: string, nombreCompleto: string, opciones: list<string>}>
     */
    private function buildApuntadosForEvento(Evento $evento, Usuario $user, ?string $search = null): array
    {
        $householdUserIds = $this->invitadoRepository->resolveHouseholdUserIds($user);
        if ($householdUserIds === []) {
            return [];
        }

        $selecciones = $this->seleccionParticipanteEventoRepository->findByEventoAndInscritoPorUsuarioIds($evento, $householdUserIds);

        $seenParticipantes = [];
        $member = [];

        foreach ($selecciones as $seleccion) {
            if (!$seleccion instanceof SeleccionParticipanteEvento) {
                continue;
            }

            $origen = $seleccion->getInvitado() !== null ? 'invitado' : 'familiar';
            $participanteId = $origen === 'invitado'
                ? (string) $seleccion->getInvitado()?->getId()
                : (string) $seleccion->getUsuario()?->getId();

            if ($participanteId === '') {
                continue;
            }

            $participantKey = sprintf('%s:%s', $origen, $participanteId);
            if (isset($seenParticipantes[$participantKey])) {
                continue;
            }

            $nombreCompleto = '';
            $inscripcionId = $participantKey;
            $opciones = [];

            if ($origen === 'familiar') {
                if (!in_array($participanteId, $householdUserIds, true)) {
                    continue;
                }

                $usuario = $this->usuarioRepository->find($participanteId);
                if ($usuario === null) {
                    continue;
                }

                $nombreCompleto = $usuario->getNombreCompleto() !== '' ? $usuario->getNombreCompleto() : self::buildNombreCompleto($usuario->getNombre(), $usuario->getApellidos());
                $inscripcion = $this->inscripcionRepository->findOneByUsuarioAndEvento($participanteId, $evento->getId());

                if ($inscripcion !== null) {
                    $inscripcionId = (string) $inscripcion->getId();
                    $opciones = $this->extractUniqueActividadOptions($inscripcion->getLineas()->toArray());
                }
            } else {
                $invitado = $this->invitadoRepository->findActiveByIdAndEventoAndHouseholdUsuario(
                    $participanteId,
                    $evento,
                    $user,
                );

                if ($invitado === null) {
                    continue;
                }

                $nombreCompleto = self::buildNombreCompleto($invitado->getNombre(), $invitado->getApellidos());

                $inscripcion = $this->inscripcionRepository->findOneByInvitadoAndEvento($participanteId, $evento->getId());
                $inscripcionId = (string) $inscripcion->getId();
                $opciones = $this->extractUniqueActividadOptions($inscripcion->getLineas()->toArray());
            }

            if ($nombreCompleto === '') {
                continue;
            }

            if (is_string($search) && !$this->matchesSearchByNombre($nombreCompleto, $search)) {
                continue;
            }

            $member[] = [
                'inscripcionId' => $inscripcionId,
                'nombreCompleto' => $nombreCompleto,
                'opciones' => $opciones,
            ];

            $seenParticipantes[$participantKey] = true;
        }

        usort(
            $member,
            static fn (array $left, array $right): int => strcasecmp($left['nombreCompleto'], $right['nombreCompleto']),
        );

        return $member;
    }

    /**
     * @param array<int, mixed> $lineas
     * @return list<string>
     */
    private function extractUniqueActividadOptions(array $lineas): array
    {
        $opciones = [];

        foreach ($lineas as $linea) {
            if (!$linea instanceof \App\Entity\InscripcionLinea) {
                continue;
            }

            if ($linea->getEstadoLinea()->value === 'cancelada') {
                continue;
            }

            $opcion = trim($linea->getNombreActividadSnapshot());
            if ($opcion !== '') {
                $opciones[$opcion] = true;
            }
        }

        return array_keys($opciones);
    }

    private function matchesSearchByNombre(string $nombreCompleto, string $search): bool
    {
        $normalizedSearch = mb_strtolower(trim($search));
        if ($normalizedSearch === '') {
            return true;
        }

        return str_contains(mb_strtolower($nombreCompleto), $normalizedSearch);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapInvitadoForEventoResponse($invitado): array
    {
        return [
            'id' => $invitado->getId(),
            'nombre' => $invitado->getNombre(),
            'apellidos' => $invitado->getApellidos(),
            'nombreCompleto' => $invitado->getNombreCompleto(),
            'tipoPersona' => $invitado->getTipoPersona()->value,
            'observaciones' => $invitado->getObservaciones(),
            'origen' => 'invitado',
            'esInvitado' => true,
            '@id' => '/api/invitados/' . $invitado->getId(),
        ];
    }
}
