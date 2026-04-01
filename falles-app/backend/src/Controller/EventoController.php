<?php

namespace App\Controller;

use App\Entity\SeleccionParticipantesEvento;
use App\Entity\Usuario;
use App\Repository\EventoRepository;
use App\Repository\InvitadoRepository;
use App\Repository\InscripcionRepository;
use App\Repository\SeleccionParticipantesEventoRepository;
use App\Repository\UsuarioRepository;
use App\Service\InscripcionService;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly SeleccionParticipantesEventoRepository $seleccionParticipantesEventoRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
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

            $seleccionParticipantesEventoRepository = $this->seleccionParticipantesEventoRepository;
            $entityManager = $this->entityManager;

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
        } catch (UnprocessableEntityHttpException $e) {
            return $this->json([
                'error' => $e->getMessage(),
                'code' => InscripcionService::ERROR_CODE_INSCRIPCION_CERRADA,
            ], 422);
        } catch (BadRequestHttpException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

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

        $seleccion = $this->seleccionParticipantesEventoRepository->findOneByUsuarioAndEvento($user, $evento);

        if ($seleccion === null) {
            return $this->json([
                'eventoId' => $evento->getId(),
                'participantes' => [],
                'updatedAt' => null,
            ]);
        }

        return $this->json([
            'eventoId' => $evento->getId(),
            'participantes' => $this->buildParticipantesSeleccionResponse($evento->getId(), $seleccion->getParticipantes()),
            'updatedAt' => $seleccion->getUpdatedAt()->format('c'),
        ]);
    }

    public function getNoFalleros(string $id): JsonResponse
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
        return $this->getNoFalleros($id);
    }

    #[Route('/eventos/{id}/apuntados', name: 'api_eventos_apuntados', methods: ['GET'])]
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

        $inscripciones = $this->inscripcionRepository->findApuntadosByEvento(
            $evento,
            is_string($search) ? $search : null,
        );

        $member = array_map(static function (\App\Entity\Inscripcion $inscripcion): array {
            $usuario = $inscripcion->getUsuario();
            $opciones = [];

            foreach ($inscripcion->getLineas() as $linea) {
                $opcion = trim($linea->getNombreMenuSnapshot());
                if ($opcion !== '') {
                    $opciones[$opcion] = true;
                }
            }

            return [
                'inscripcionId' => $inscripcion->getId(),
                'nombreCompleto' => self::buildNombreCompleto($usuario->getNombre(), $usuario->getApellidos()),
                'opciones' => array_keys($opciones),
            ];
        }, $inscripciones);

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

        $seleccionParticipantesEventoRepository = $this->seleccionParticipantesEventoRepository;
        $entityManager = $this->entityManager;

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

        $seleccion = $this->seleccionParticipantesEventoRepository->findOneByUsuarioAndEvento($user, $evento);

        if ($seleccion !== null) {
            $this->entityManager->remove($seleccion);
            $this->entityManager->flush();
        }

        return $this->json([], 204);
    }

    /**
     * @param list<array<string, mixed>> $participantes
     * @return list<array<string, mixed>>
     */
    private function buildParticipantesSeleccionResponse(string $eventoId, array $participantes): array
    {
        $response = [];

        foreach ($participantes as $participante) {
            if (!is_array($participante)) {
                continue;
            }

            $origen = ($participante['origen'] ?? null) === 'no_fallero' ? 'no_fallero' : 'familiar';
            $participanteId = is_string($participante['id'] ?? null) ? trim($participante['id']) : '';

            if ($participanteId === '') {
                continue;
            }

            $item = [
                'id' => $participanteId,
                'origen' => $origen,
            ];

            if ($origen === 'familiar') {
                $usuario = $this->usuarioRepository->find($participanteId);
                if ($usuario !== null) {
                    $item['nombre'] = $usuario->getNombre();
                    $item['apellidos'] = $usuario->getApellidos();

                    $inscripcion = $this->inscripcionRepository->findOneByUsuarioAndEvento($usuario->getId(), $eventoId);
                    if ($inscripcion !== null) {
                        $lineas = [];
                        foreach ($inscripcion->getLineas() as $linea) {
                            $lineas[] = [
                                'id' => $linea->getId(),
                                'nombreMenuSnapshot' => $linea->getNombreMenuSnapshot(),
                                'franjaComidaSnapshot' => $linea->getFranjaComidaSnapshot(),
                                'estadoLinea' => $linea->getEstadoLinea()->value,
                                'precioUnitario' => $linea->getPrecioUnitario(),
                            ];
                        }

                        $item['inscripcionRelacion'] = [
                            'id' => $inscripcion->getId(),
                            'codigo' => $inscripcion->getCodigo(),
                            'estadoPago' => $inscripcion->getEstadoPago()->value,
                            'lineas' => $lineas,
                        ];
                    }
                }
            }

            $response[] = $item;
        }

        return $response;
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
            'origen' => 'no_fallero',
            'esNoFallero' => true,
            '@id' => '/api/invitados/' . $invitado->getId(),
        ];
    }

}
