<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\SeleccionParticipantesInput;
use App\Dto\SeleccionParticipantesView;
use App\Entity\Evento;
use App\Entity\InscripcionLinea;
use App\Entity\Invitado;
use App\Entity\ActividadEvento;
use App\Entity\SeleccionParticipanteEvento;
use App\Entity\SeleccionParticipanteEventoLinea;
use App\Entity\Usuario;
use App\Enum\EstadoLineaInscripcionEnum;
use App\Repository\EventoRepository;
use App\Repository\InscripcionRepository;
use App\Repository\InvitadoRepository;
use App\Repository\SeleccionParticipanteEventoLineaRepository;
use App\Repository\SeleccionParticipanteEventoRepository;
use App\Repository\UsuarioRepository;
use App\Service\EmailQueueService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SeleccionParticipantesEventoPutProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EventoRepository $eventoRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SeleccionParticipanteEventoRepository $seleccionParticipanteEventoRepository,
        private readonly SeleccionParticipanteEventoLineaRepository $seleccionParticipanteEventoLineaRepository,
        private readonly UsuarioRepository $usuarioRepository,
        private readonly InvitadoRepository $invitadoRepository,
        private readonly InscripcionRepository $inscripcionRepository,
        private readonly EmailQueueService $emailQueueService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SeleccionParticipantesView
    {
        if (!$data instanceof SeleccionParticipantesInput) {
            throw new BadRequestHttpException('Payload inválido.');
        }

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
            if ($data->participantes !== []) {
                throw new BadRequestHttpException('Este evento no tiene actividades activas y no admite selección de participantes.');
            }

            $response = new SeleccionParticipantesView();
            $response->eventoId = $evento->getId();
            $response->participantes = [];
            $response->updatedAt = null;

            return $response;
        }

        $participantes = [];
        foreach ($data->participantes as $index => $participante) {
            if (!is_array($participante)) {
                throw new BadRequestHttpException(sprintf('Participante inválido en índice %d', $index));
            }

            $rawParticipanteId = $participante['id'] ?? null;
            $origenInferido = $this->inferOrigenFromParticipanteReference($rawParticipanteId);
            $origen = $this->resolveOrigen($participante['origen'] ?? null, $origenInferido, $index);
            $participanteId = $this->normalizeParticipanteId($rawParticipanteId);

            if ($participanteId === '') {
                throw new BadRequestHttpException(sprintf('ID de participante inválido en índice %d', $index));
            }

            $participantes[] = [
                'id' => $participanteId,
                'origen' => $this->normalizeOrigen($origen),
            ];
        }

        if (!$evento->permiteGestionInvitadosConActividades()) {
            foreach ($participantes as $participante) {
                if (($participante['origen'] ?? 'familiar') === 'invitado') {
                    throw new BadRequestHttpException('Este evento no permite invitados.');
                }
            }
        }

        $householdUserIds = $this->invitadoRepository->resolveHouseholdUserIds($user);
        $householdUserIdsMap = array_fill_keys($householdUserIds, true);
        $deseadasPorClave = $this->buildDesiredPrincipalSelections(
            $participantes,
            $evento,
            $user,
            $householdUserIdsMap,
            $this->usuarioRepository,
            $this->invitadoRepository,
        );

        $seleccionesPrincipales = $this->syncPrincipalSelection(
            $deseadasPorClave,
            $evento,
            $user,
            $this->seleccionParticipanteEventoRepository,
        );

        // Prefetch inscripciones activas for evento and map the most recent by usuario and invitado
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

        $this->syncLineaSelection(
            $seleccionesPrincipales,
            $evento,
            $this->seleccionParticipanteEventoLineaRepository,
            $inscripcionesPorUsuario,
            $inscripcionesPorInvitado,
        );

        $inscripcionTitular = $this->inscripcionRepository->findOneByUsuarioAndEvento($user->getId(), $evento->getId());
        if ($inscripcionTitular !== null) {
            $this->emailQueueService->enqueueInscripcionCambio($inscripcionTitular, 'actualizado');
        }

        $this->entityManager->flush();
        $updatedAt = $seleccionesPrincipales[0]?->getUpdatedAt() ?? null;

        $response = new SeleccionParticipantesView();
        $response->eventoId = $evento->getId();
        $response->participantes = $this->buildLegacySnapshotFromPrincipal($seleccionesPrincipales);
        $response->updatedAt = $updatedAt?->format('c');

        // Build inscripciones snapshot for the selected participants (avoid duplicates)
        // Prefetch all active inscripciones for the evento to avoid N+1 DB queries
        $inscripcionesMap = [];

        $inscripciones = $this->inscripcionRepository->findApuntadosByEvento($evento);
        $inscripcionesPorUsuario = [];
        $inscripcionesPorInvitado = [];

        foreach ($inscripciones as $insc) {
            $u = $insc->getUsuario();
            if ($u !== null && $u->getId() !== null) {
                $inscripcionesPorUsuario[$u->getId()] = $insc;
            }

            foreach ($insc->getLineas() as $linea) {
                $inv = $linea->getInvitado();
                if ($inv !== null && $inv->getId() !== null && !isset($inscripcionesPorInvitado[$inv->getId()])) {
                    // map invitado id to the inscripcion that contains it (first occurrence)
                    $inscripcionesPorInvitado[$inv->getId()] = $insc;
                }
            }
        }

        foreach ($seleccionesPrincipales as $seleccion) {
            $usuario = $seleccion->getUsuario();
            $invitado = $seleccion->getInvitado();

            $inscripcion = null;

            if ($usuario !== null) {
                $inscripcion = $inscripcionesPorUsuario[$usuario->getId()] ?? null;
            } elseif ($invitado !== null) {
                $inscripcion = $inscripcionesPorInvitado[$invitado->getId()] ?? null;
            }

            if ($inscripcion === null) {
                continue;
            }

            $inscId = $inscripcion->getId();
            if ($inscId === null || isset($inscripcionesMap[$inscId])) {
                continue;
            }

            $lineas = [];
            foreach ($inscripcion->getLineas() as $linea) {
                $actividadId = $linea->getActividadId();

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

            $inscripcionesMap[$inscId] = [
                'id' => $inscId,
                'evento' => $eventoSnapshot,
                'estadoInscripcion' => $inscripcion->getEstadoInscripcion()->value,
                'estadoPago' => $inscripcion->getEstadoPago()->value,
                'importeTotal' => $inscripcion->getImporteTotal(),
                'moneda' => $inscripcion->getMoneda(),
                'lineas' => $lineas,
            ];
        }

        // Preserve ordering: use values
        $response->inscripciones = array_values($inscripcionesMap);

        return $response;
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

    private function inferOrigenFromParticipanteReference(mixed $rawId): ?string
    {
        if (!is_string($rawId)) {
            return null;
        }

        $cleaned = trim($rawId);

        if (str_starts_with($cleaned, '/api/invitados/')) {
            return 'invitado';
        }

        if (str_starts_with($cleaned, '/api/usuarios/')) {
            return 'familiar';
        }

        return null;
    }

    private function resolveOrigen(mixed $origenRaw, ?string $origenInferido, int $index): string
    {
        $origenExplicito = is_string($origenRaw) && in_array($origenRaw, ['familiar', 'invitado'], true)
            ? $this->normalizeOrigen($origenRaw)
            : null;

        if ($origenExplicito !== null && $origenInferido !== null && $origenExplicito !== $origenInferido) {
            throw new BadRequestHttpException(sprintf('Origen inconsistente con ID en índice %d', $index));
        }

        if ($origenInferido !== null) {
            return $origenInferido;
        }

        if ($origenExplicito !== null) {
            return $origenExplicito;
        }

        throw new BadRequestHttpException(sprintf('Origen inválido en índice %d', $index));
    }

    /**
     * @param list<array{id: string, origen: string}> $participantes
     * @param array<string, true> $householdUserIdsMap
     * @return array<string, array{usuario: ?Usuario, invitado: ?Invitado}>
     */
    private function buildDesiredPrincipalSelections(
        array $participantes,
        Evento $evento,
        Usuario $inscritoPor,
        array $householdUserIdsMap,
        UsuarioRepository $usuarioRepository,
        InvitadoRepository $invitadoRepository,
    ): array {
        $deseadasPorClave = [];

        foreach ($participantes as $participante) {
            $origen = $this->normalizeOrigen($participante['origen'] ?? null);
            $participanteId = $this->normalizeParticipanteId($participante['id'] ?? null);

            if ($participanteId === '') {
                continue;
            }

            if ($origen === 'invitado') {
                $invitado = $invitadoRepository->findActiveByIdAndEventoAndHouseholdUsuario($participanteId, $evento, $inscritoPor);
                if ($invitado === null) {
                    continue;
                }

                $clave = 'invitado|' . $invitado->getId();
                $deseadasPorClave[$clave] = [
                    'usuario' => null,
                    'invitado' => $invitado,
                ];

                continue;
            }

            if (!isset($householdUserIdsMap[$participanteId])) {
                continue;
            }

            $usuario = $usuarioRepository->find($participanteId);
            if ($usuario === null || $usuario->getEntidad()->getId() !== $evento->getEntidad()->getId()) {
                continue;
            }

            $clave = 'familiar|' . $usuario->getId();
            $deseadasPorClave[$clave] = [
                'usuario' => $usuario,
                'invitado' => null,
            ];
        }

        return $deseadasPorClave;
    }

    /**
     * @param array<string, array{usuario: ?Usuario, invitado: ?Invitado}> $deseadasPorClave
     * @return list<SeleccionParticipanteEvento>
     */
    private function syncPrincipalSelection(
        array $deseadasPorClave,
        Evento $evento,
        Usuario $user,
        SeleccionParticipanteEventoRepository $seleccionParticipanteEventoRepository,
    ): array {
        $existentes = $seleccionParticipanteEventoRepository->findByEventoAndInscritoPorUsuario($evento, $user);
        $existentesPorClave = [];

        foreach ($existentes as $existente) {
            $existentesPorClave[$this->buildPrincipalKeyFromEntity($existente)] = $existente;
        }

        foreach ($existentesPorClave as $clave => $existente) {
            if (!isset($deseadasPorClave[$clave])) {
                $this->entityManager->remove($existente);
            }
        }

        $selecciones = [];
        foreach ($deseadasPorClave as $clave => $deseada) {
            $existente = $existentesPorClave[$clave] ?? null;
            if ($existente instanceof SeleccionParticipanteEvento) {
                $existente->setUsuario($deseada['usuario']);
                $existente->setInvitado($deseada['invitado']);
                $selecciones[] = $existente;

                continue;
            }

            $nueva = new SeleccionParticipanteEvento();
            $nueva->setEvento($evento);
            $nueva->setInscritoPorUsuario($user);
            $nueva->setUsuario($deseada['usuario']);
            $nueva->setInvitado($deseada['invitado']);
            $this->entityManager->persist($nueva);
            $selecciones[] = $nueva;
        }

        return $selecciones;
    }

    /**
     * @param list<SeleccionParticipanteEvento> $seleccionesPrincipales
     */
    /**
     * @param list<SeleccionParticipanteEvento> $seleccionesPrincipales
     * @param array<string, Inscripcion> $inscripcionesPorUsuario
     * @param array<string, Inscripcion> $inscripcionesPorInvitado
     */
    private function syncLineaSelection(
        array $seleccionesPrincipales,
        Evento $evento,
        SeleccionParticipanteEventoLineaRepository $seleccionParticipanteEventoLineaRepository,
        array $inscripcionesPorUsuario,
        array $inscripcionesPorInvitado,
    ): void {
        foreach ($seleccionesPrincipales as $seleccionPrincipal) {
            $lineasExistentes = $seleccionParticipanteEventoLineaRepository->findBySeleccionParticipanteEvento($seleccionPrincipal);
            $lineasExistentesPorActividadId = [];

            foreach ($lineasExistentes as $lineaExistente) {
                $actividadId = (string) $lineaExistente->getActividad()->getId();
                if ($actividadId !== '') {
                    $lineasExistentesPorActividadId[$actividadId] = $lineaExistente;
                }
            }

            $lineasDeseadasPorActividadId = $this->buildDesiredLineSelections(
                $evento,
                $seleccionPrincipal,
                $inscripcionesPorUsuario,
                $inscripcionesPorInvitado,
            );

            foreach ($lineasExistentesPorActividadId as $actividadId => $lineaExistente) {
                if (!isset($lineasDeseadasPorActividadId[$actividadId])) {
                    $this->entityManager->remove($lineaExistente);
                }
            }

            foreach ($lineasDeseadasPorActividadId as $actividadId => $lineaDeseada) {
                $lineaExistente = $lineasExistentesPorActividadId[$actividadId] ?? null;

                if ($lineaExistente instanceof SeleccionParticipanteEventoLinea) {
                    $this->hydrateLineaSelection($lineaExistente, $lineaDeseada);
                    continue;
                }

                $nuevaLinea = new SeleccionParticipanteEventoLinea();
                $nuevaLinea->setSeleccionParticipanteEvento($seleccionPrincipal);
                $this->hydrateLineaSelection($nuevaLinea, $lineaDeseada);
                $this->entityManager->persist($nuevaLinea);
            }
        }
    }

    /**
     * @return array<string, array{evento: Evento, usuario: ?Usuario, invitado: ?Invitado, actividad: ActividadEvento, inscripcionLinea: ?InscripcionLinea}>
     */
    /**
     * @param array<string, Inscripcion> $inscripcionesPorUsuario
     * @param array<string, Inscripcion> $inscripcionesPorInvitado
     * @return array<string, array{evento: Evento, usuario: ?Usuario, invitado: ?Invitado, actividad: ActividadEvento, inscripcionLinea: ?InscripcionLinea}>
     */
    private function buildDesiredLineSelections(
        Evento $evento,
        SeleccionParticipanteEvento $seleccionPrincipal,
        array $inscripcionesPorUsuario,
        array $inscripcionesPorInvitado,
    ): array {
        $lineasInscripcion = $this->resolveLineasParticipante($evento, $seleccionPrincipal, $inscripcionesPorUsuario, $inscripcionesPorInvitado);
        $lineasDeseadas = [];

        foreach ($lineasInscripcion as $lineaInscripcion) {
            $actividadId = (string) $lineaInscripcion->getActividad()->getId();
            if ($actividadId === '' || isset($lineasDeseadas[$actividadId])) {
                continue;
            }

            $lineasDeseadas[$actividadId] = [
                'evento' => $evento,
                'usuario' => $seleccionPrincipal->getUsuario(),
                'invitado' => $seleccionPrincipal->getInvitado(),
                'actividad' => $lineaInscripcion->getActividad(),
                'inscripcionLinea' => $lineaInscripcion,
            ];
        }

        return $lineasDeseadas;
    }

    /**
     * @return list<InscripcionLinea>
     */
    private function resolveLineasParticipante(
        Evento $evento,
        SeleccionParticipanteEvento $seleccionPrincipal,
        array $inscripcionesPorUsuario,
        array $inscripcionesPorInvitado,
    ): array {
        $usuario = $seleccionPrincipal->getUsuario();
        if ($usuario !== null) {
            $inscripcion = $inscripcionesPorUsuario[$usuario->getId()] ?? null;
            if ($inscripcion === null) {
                return [];
            }

            $lineas = [];
            foreach ($inscripcion->getLineas() as $linea) {
                if ($linea->getEstadoLinea() === EstadoLineaInscripcionEnum::CANCELADA) {
                    continue;
                }

                if ($linea->getUsuario()?->getId() !== $usuario->getId()) {
                    continue;
                }

                $lineas[] = $linea;
            }

            return $lineas;
        }

        $invitado = $seleccionPrincipal->getInvitado();
        if ($invitado === null) {
            return [];
        }

        $inscripcion = $inscripcionesPorInvitado[$invitado->getId()] ?? null;
        if ($inscripcion === null) {
            return [];
        }

        $lineas = [];
        foreach ($inscripcion->getLineas() as $linea) {
            if ($linea->getEstadoLinea() === EstadoLineaInscripcionEnum::CANCELADA) {
                continue;
            }

            if ($linea->getInvitado()?->getId() !== $invitado->getId()) {
                continue;
            }

            $lineas[] = $linea;
        }

        return $lineas;
    }

    /**
     * @param array{evento: Evento, usuario: ?Usuario, invitado: ?Invitado, actividad: ActividadEvento, inscripcionLinea: ?InscripcionLinea} $data
     */
    private function hydrateLineaSelection(SeleccionParticipanteEventoLinea $linea, array $data): void
    {
        $linea->setEvento($data['evento']);
        $linea->setUsuario($data['usuario']);
        $linea->setInvitado($data['invitado']);
        $linea->setActividad($data['actividad']);
        $linea->setInscripcionLinea($data['inscripcionLinea']);
    }

    /**
     * @param list<SeleccionParticipanteEvento> $selecciones
     * @return list<array{id: string, origen: string}>
     */
    private function buildLegacySnapshotFromPrincipal(array $selecciones): array
    {
        $participantes = [];

        foreach ($selecciones as $seleccion) {
            $origen = $seleccion->getInvitado() !== null ? 'invitado' : 'familiar';
            $participanteId = $origen === 'invitado'
                ? (string) $seleccion->getInvitado()?->getId()
                : (string) $seleccion->getUsuario()?->getId();

            if ($participanteId === '') {
                continue;
            }

            $participantes[] = [
                'id' => $participanteId,
                'origen' => $origen,
            ];
        }

        return $participantes;
    }

    private function buildPrincipalKeyFromEntity(SeleccionParticipanteEvento $seleccion): string
    {
        $origen = $seleccion->getInvitado() !== null ? 'invitado' : 'familiar';
        $participanteId = $origen === 'invitado'
            ? (string) $seleccion->getInvitado()?->getId()
            : (string) $seleccion->getUsuario()?->getId();

        return $origen . '|' . $participanteId;
    }
}
