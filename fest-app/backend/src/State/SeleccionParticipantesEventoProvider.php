<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\SeleccionParticipantesView;
use App\Entity\Evento;
use App\Entity\Inscripcion;
use App\Entity\SeleccionParticipanteEvento;
use App\Entity\Usuario;
use App\Enum\EstadoLineaInscripcionEnum;
use App\Repository\EventoRepository;
use App\Repository\InscripcionRepository;
use App\Repository\InvitadoRepository;
use App\Repository\SeleccionParticipanteEventoRepository;
use App\Repository\UsuarioRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SeleccionParticipantesEventoProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EventoRepository $eventoRepository,
        private readonly UsuarioRepository $usuarioRepository,
        private readonly InscripcionRepository $inscripcionRepository,
        private readonly InvitadoRepository $invitadoRepository,
        private readonly SeleccionParticipanteEventoRepository $seleccionParticipanteEventoRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): SeleccionParticipantesView
    {
        $user = $this->security->getUser();

        if (!$user instanceof Usuario) {
            throw new AccessDeniedHttpException('No autenticado.');
        }

        $eventoId = is_string($uriVariables['eventoId'] ?? null) ? $uriVariables['eventoId'] : null;
        $evento = $eventoId !== null ? $this->eventoRepository->find($eventoId) : null;

        if ($evento === null) {
            throw new NotFoundHttpException('Evento no encontrado.');
        }

        if ($evento->getEntidad()->getId() !== $user->getEntidad()->getId()) {
            throw new AccessDeniedHttpException('No tienes acceso a este evento.');
        }

        if (!$evento->tieneActividadesActivas()) {
            $response = new SeleccionParticipantesView();
            $response->eventoId = $evento->getId();
            $response->participantes = [];
            $response->updatedAt = null;

            return $response;
        }

        $seleccionGranular = $this->seleccionParticipanteEventoRepository->findByEventoAndInscritoPorUsuario($evento, $user);
        $participantes = $this->buildParticipantesFromGranular($seleccionGranular);

        if (!$evento->permiteGestionInvitadosConActividades()) {
            $participantes = array_values(array_filter(
                $participantes,
                static fn(array $participante): bool => ($participante['origen'] ?? 'familiar') !== 'invitado',
            ));
        }

        // Prefetch inscripciones activas para el evento and map by most recent createdAt per usuario/invitado
        $inscripciones = $this->inscripcionRepository->findApuntadosByEvento($evento);
        $inscripcionesPorUsuario = [];
        $inscripcionesPorInvitado = [];

        foreach ($inscripciones as $insc) {
            $createdAt = $insc->getCreatedAt();

            $u = $insc->getUsuario();
            if ($u !== null && $u->getId() !== null) {
                $uid = $u->getId();
                $existing = $inscripcionesPorUsuario[$uid] ?? null;
                if ($existing === null || ($createdAt !== null && $existing->getCreatedAt() !== null && $createdAt > $existing->getCreatedAt())) {
                    $inscripcionesPorUsuario[$uid] = $insc;
                }
            }

            foreach ($insc->getLineas() as $linea) {
                $inv = $linea->getInvitado();
                if ($inv === null || $inv->getId() === null) {
                    continue;
                }

                $iid = $inv->getId();
                $existingInv = $inscripcionesPorInvitado[$iid] ?? null;
                if ($existingInv === null) {
                    $inscripcionesPorInvitado[$iid] = $insc;
                    continue;
                }

                $existingCreated = $existingInv->getCreatedAt();
                if ($createdAt !== null && $existingCreated !== null && $createdAt > $existingCreated) {
                    $inscripcionesPorInvitado[$iid] = $insc;
                }
            }
        }

        $response = new SeleccionParticipantesView();
        $response->eventoId = $evento->getId();
        $response->participantes = $this->buildParticipantesSeleccionResponse($evento->getId(), $participantes, $evento, $user, $inscripcionesPorUsuario, $inscripcionesPorInvitado);
        $response->updatedAt = $this->resolveUpdatedAtFromGranular($seleccionGranular);

        // Include current user's inscripcion snapshot (if any)
        $inscPropia = $inscripcionesPorUsuario[$user->getId()] ?? null;
        if ($inscPropia !== null) {
            $lineas = [];
            foreach ($inscPropia->getLineas() as $linea) {
                $actividadId = $linea->getActividad()?->getId();

                $lineas[] = [
                    'id' => $linea->getId(),
                    'usuario' => $linea->getUsuario()?->getId() ? '/api/usuarios/' . $linea->getUsuario()?->getId() : null,
                    'invitado' => $linea->getInvitado()?->getId() ? '/api/invitados/' . $linea->getInvitado()?->getId() : null,
                    'actividad' => $actividadId ? '/api/actividad_eventos/' . $actividadId : null,
                    'nombrePersonaSnapshot' => $linea->getNombrePersonaSnapshot(),
                    'tipoPersonaSnapshot' => $linea->getTipoPersonaSnapshot(),
                    'nombreActividadSnapshot' => $linea->getNombreActividadSnapshot(),
                    'franjaComidaSnapshot' => $linea->getFranjaComidaSnapshot(),
                    'precioUnitario' => $linea->getPrecioUnitario(),
                    'estadoLinea' => $linea->getEstadoLinea()->value,
                    'pagada' => $linea->isPagada(),
                    'actividadId' => $actividadId,
                ];
            }

            $eventoSnapshot = [
                'id' => $evento->getId(),
                'titulo' => $evento->getTitulo(),
                'descripcion' => $evento->getDescripcion(),
                'fechaEvento' => $evento->getFechaEvento()->format('c'),
                'horaInicio' => $evento->getHoraInicio()?->format('c'),
                'lugar' => $evento->getLugar(),
                'estado' => $evento->getEstado()->value,
                'inscripcionAbierta' => $evento->getInscripcionAbierta(),
            ];

            $response->inscripciones = [[
                'id' => $inscPropia->getId(),
                'evento' => $eventoSnapshot,
                'estadoInscripcion' => $inscPropia->getEstadoInscripcion()->value,
                'estadoPago' => $inscPropia->getEstadoPago()->value,
                'importeTotal' => $inscPropia->getImporteTotal(),
                'moneda' => $inscPropia->getMoneda(),
                'lineas' => $lineas,
            ]];
        } else {
            $response->inscripciones = [];
        }

        return $response;
    }

    /**
     * Builds the list of actividades for the evento with a boolean indicating if the
     * participant is enrolled in each one.
     *
     * @param list<array<string, mixed>> $lineasActivas Active inscription lines for the participant
     * @return list<array<string, mixed>>
     */
    private function buildActividadesSeleccionadas(Evento $evento, array $lineasActivas): array
    {
        $actividadesInscritas = [];
        foreach ($lineasActivas as $linea) {
            $actividadId = $linea['actividadId'] ?? null;
            if ($actividadId !== null) {
                $actividadesInscritas[$actividadId] = true;
            }
        }

        $result = [];
        foreach ($evento->getActividades() as $actividad) {
            if ($actividad->isActivo() === false) {
                continue;
            }

            $actividadId = $actividad->getId();
            $result[] = [
                'id'               => $actividadId,
                'nombre'           => $actividad->getNombre(),
                'franjaComida'     => $actividad->getFranjaComida(),
                'tipoActividad'    => $actividad->getTipoActividad(),
                'compatibilidad'   => $actividad->getCompatibilidadPersona(),
                'inscrito'         => isset($actividadesInscritas[$actividadId]),
            ];
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $participantes
     * @return list<array<string, mixed>>
     */
    /**
     * @param array<string, Inscripcion> $inscripcionesPorUsuario
     * @param array<string, Inscripcion> $inscripcionesPorInvitado
     */
    private function buildParticipantesSeleccionResponse(string $eventoId, array $participantes, Evento $evento, Usuario $user, array $inscripcionesPorUsuario = [], array $inscripcionesPorInvitado = []): array
    {
        $response = [];

        foreach ($participantes as $participante) {
            if (!is_array($participante)) {
                continue;
            }

            $origen = $this->normalizeOrigen($participante['origen'] ?? null);
            $participanteId = $this->normalizeParticipanteId($participante['id'] ?? null);

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

                    $inscripcion = $inscripcionesPorUsuario[$usuario->getId()] ?? null;
                    $item['tipoPersona'] = 'adulto';

                    $inscripcionRelacion = $this->buildInscripcionRelacion(
                        $inscripcion,
                        static fn($linea): bool => $linea->getUsuario()?->getId() === $usuario->getId(),
                    );

                    if ($inscripcionRelacion !== null) {
                        $item['inscripcionRelacion'] = $inscripcionRelacion;

                        $tipoDesdeSnapshot = $this->resolveTipoPersonaFromInscripcion($inscripcionRelacion['lineas']);
                        if ($tipoDesdeSnapshot !== null) {
                            $item['tipoPersona'] = $tipoDesdeSnapshot;
                        }

                        $lineasActivas = array_values(array_filter(
                            $inscripcionRelacion['lineas'],
                            static fn(array $l): bool => ($l['estadoLinea'] ?? '') !== 'cancelada',
                        ));
                        $item['estaInscrito'] = count($lineasActivas) > 0;
                        $item['actividadesSeleccionadas'] = $this->buildActividadesSeleccionadas($evento, $lineasActivas);
                    } else {
                        $item['estaInscrito'] = false;
                        $item['actividadesSeleccionadas'] = $this->buildActividadesSeleccionadas($evento, []);
                    }
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

                $item['nombre'] = $invitado->getNombre();
                $item['apellidos'] = $invitado->getApellidos();
                $item['tipoPersona'] = $invitado->getTipoPersona()->value;

                $inscripcionInvitado = $inscripcionesPorInvitado[$invitado->getId()] ?? null;
                $inscripcionRelacion = $this->buildInscripcionRelacion(
                    $inscripcionInvitado,
                    static fn($linea): bool => $linea->getInvitado()?->getId() === $invitado->getId(),
                );

                if ($inscripcionRelacion !== null) {
                    $item['inscripcionRelacion'] = $inscripcionRelacion;

                    $lineasActivas = array_values(array_filter(
                        $inscripcionRelacion['lineas'],
                        static fn(array $l): bool => ($l['estadoLinea'] ?? '') !== 'cancelada',
                    ));
                    $item['estaInscrito'] = count($lineasActivas) > 0;
                    $item['actividadesSeleccionadas'] = $this->buildActividadesSeleccionadas($evento, $lineasActivas);
                } else {
                    $item['estaInscrito'] = false;
                    $item['actividadesSeleccionadas'] = $this->buildActividadesSeleccionadas($evento, []);
                }
            }

            $response[] = $item;
        }

        return $response;
    }

    private function buildInscripcionRelacion(?Inscripcion $inscripcion, callable $lineFilter): ?array
    {
        if ($inscripcion === null) {
            return null;
        }

        $lineas = [];
        $totalLineas = 0.0;

        foreach ($inscripcion->getLineas() as $linea) {
            if (!$lineFilter($linea)) {
                continue;
            }

            if ($linea->getEstadoLinea() === EstadoLineaInscripcionEnum::CANCELADA) {
                continue;
            }

            $precioUnitario = $linea->getPrecioUnitario();
            $totalLineas += $precioUnitario;

            $lineas[] = [
                'id' => $linea->getId(),
                'actividadId' => $linea->getActividad()->getId(),
                'usuarioId' => $linea->getUsuario()?->getId(),
                'invitadoId' => $linea->getInvitado()?->getId(),
                'nombreActividadSnapshot' => $linea->getNombreActividadSnapshot(),
                'franjaComidaSnapshot' => $linea->getFranjaComidaSnapshot(),
                'estadoLinea' => $linea->getEstadoLinea()->value,
                'pagada' => $linea->isPagada(),
                'precioUnitario' => $precioUnitario,
                'tipoPersonaSnapshot' => $linea->getTipoPersonaSnapshot(),
            ];
        }

        if ($lineas === []) {
            return null;
        }

        return [
            'id' => $inscripcion->getId(),
            'codigo' => $inscripcion->getCodigo(),
            'estadoPago' => $inscripcion->getEstadoPago()->value,
            'totalLineas' => round($totalLineas, 2),
            'totalPagado' => $inscripcion->getImportePagado(),
            'lineas' => $lineas,
        ];
    }

    /**
     * @param list<array<string, mixed>> $lineas
     */
    private function resolveTipoPersonaFromInscripcion(array $lineas): ?string
    {
        foreach ($lineas as $linea) {
            $tipo = is_string($linea['tipoPersonaSnapshot'] ?? null)
                ? trim((string) $linea['tipoPersonaSnapshot'])
                : '';

            if ($tipo === 'adulto' || $tipo === 'infantil') {
                return $tipo;
            }
        }

        return null;
    }

    private function normalizeParticipanteId(mixed $rawId): string
    {
        if (!is_string($rawId)) {
            return '';
        }

        $cleaned = trim($rawId);
        if ($cleaned === '') {
            return '';
        }

        if (!str_contains($cleaned, '/')) {
            return $cleaned;
        }

        $parts = array_values(array_filter(explode('/', trim($cleaned, '/'))));

        return $parts === [] ? '' : (string) end($parts);
    }

    private function normalizeOrigen(mixed $origen): string
    {
        if ($origen === 'invitado') {
            return 'invitado';
        }

        return 'familiar';
    }

    /**
     * @param list<SeleccionParticipanteEvento> $seleccionGranular
     * @return list<array{id: string, origen: string}>
     */
    private function buildParticipantesFromGranular(array $seleccionGranular): array
    {
        $participantes = [];
        $seen = [];

        foreach ($seleccionGranular as $seleccion) {
            $origen = $seleccion->getInvitado() !== null ? 'invitado' : 'familiar';
            $participanteId = $origen === 'invitado'
                ? (string) $seleccion->getInvitado()?->getId()
                : (string) $seleccion->getUsuario()?->getId();

            if ($participanteId === '') {
                continue;
            }

            $clave = $origen . '|' . $participanteId;
            if (isset($seen[$clave])) {
                continue;
            }

            $seen[$clave] = true;
            // Include the SeleccionParticipanteEvento id so the frontend can
            // reference the exact selection resource for DELETE operations.
            $participantes[] = [
                'id' => $participanteId,
                'origen' => $origen,
                'seleccionId' => $seleccion->getId(),
            ];
        }

        return $participantes;
    }

    /**
     * @param list<SeleccionParticipanteEvento> $seleccionGranular
     */
    private function resolveUpdatedAtFromGranular(array $seleccionGranular): ?string
    {
        if ($seleccionGranular === []) {
            return null;
        }

        $updatedAt = $seleccionGranular[0]?->getUpdatedAt() ?? null;

        return $updatedAt?->format('c');
    }
}
