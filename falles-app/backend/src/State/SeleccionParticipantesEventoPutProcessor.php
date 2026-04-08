<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\SeleccionParticipantesInput;
use App\Dto\SeleccionParticipantesView;
use App\Entity\Evento;
use App\Entity\InscripcionLinea;
use App\Entity\Invitado;
use App\Entity\MenuEvento;
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

        if (!$evento->tieneMenusActivos()) {
            if ($data->participantes !== []) {
                throw new BadRequestHttpException('Este evento no tiene comidas activas y no admite selección de participantes.');
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

        if (!$evento->permiteGestionInvitados()) {
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

        $this->syncLineaSelection(
            $seleccionesPrincipales,
            $evento,
            $this->seleccionParticipanteEventoLineaRepository,
            $this->inscripcionRepository,
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
    private function syncLineaSelection(
        array $seleccionesPrincipales,
        Evento $evento,
        SeleccionParticipanteEventoLineaRepository $seleccionParticipanteEventoLineaRepository,
        InscripcionRepository $inscripcionRepository,
    ): void {
        foreach ($seleccionesPrincipales as $seleccionPrincipal) {
            $lineasExistentes = $seleccionParticipanteEventoLineaRepository->findBySeleccionParticipanteEvento($seleccionPrincipal);
            $lineasExistentesPorMenuId = [];

            foreach ($lineasExistentes as $lineaExistente) {
                $menuId = (string) $lineaExistente->getMenu()->getId();
                if ($menuId !== '') {
                    $lineasExistentesPorMenuId[$menuId] = $lineaExistente;
                }
            }

            $lineasDeseadasPorMenuId = $this->buildDesiredLineSelections(
                $evento,
                $seleccionPrincipal,
                $inscripcionRepository,
            );

            foreach ($lineasExistentesPorMenuId as $menuId => $lineaExistente) {
                if (!isset($lineasDeseadasPorMenuId[$menuId])) {
                    $this->entityManager->remove($lineaExistente);
                }
            }

            foreach ($lineasDeseadasPorMenuId as $menuId => $lineaDeseada) {
                $lineaExistente = $lineasExistentesPorMenuId[$menuId] ?? null;

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
     * @return array<string, array{evento: Evento, usuario: ?Usuario, invitado: ?Invitado, menu: MenuEvento, inscripcionLinea: ?InscripcionLinea}>
     */
    private function buildDesiredLineSelections(
        Evento $evento,
        SeleccionParticipanteEvento $seleccionPrincipal,
        InscripcionRepository $inscripcionRepository,
    ): array {
        $lineasInscripcion = $this->resolveLineasParticipante($evento, $seleccionPrincipal, $inscripcionRepository);
        $lineasDeseadas = [];

        foreach ($lineasInscripcion as $lineaInscripcion) {
            $menuId = (string) $lineaInscripcion->getMenu()->getId();
            if ($menuId === '' || isset($lineasDeseadas[$menuId])) {
                continue;
            }

            $lineasDeseadas[$menuId] = [
                'evento' => $evento,
                'usuario' => $seleccionPrincipal->getUsuario(),
                'invitado' => $seleccionPrincipal->getInvitado(),
                'menu' => $lineaInscripcion->getMenu(),
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
        InscripcionRepository $inscripcionRepository,
    ): array {
        $usuario = $seleccionPrincipal->getUsuario();
        if ($usuario !== null) {
            $inscripcion = $inscripcionRepository->findOneByUsuarioAndEvento($usuario->getId(), $evento->getId());
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

        $inscripcion = $inscripcionRepository->findOneByInvitadoAndEvento($invitado->getId(), $evento->getId());
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
     * @param array{evento: Evento, usuario: ?Usuario, invitado: ?Invitado, menu: MenuEvento, inscripcionLinea: ?InscripcionLinea} $data
     */
    private function hydrateLineaSelection(SeleccionParticipanteEventoLinea $linea, array $data): void
    {
        $linea->setEvento($data['evento']);
        $linea->setUsuario($data['usuario']);
        $linea->setInvitado($data['invitado']);
        $linea->setMenu($data['menu']);
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
