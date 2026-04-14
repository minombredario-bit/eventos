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

        $response = new SeleccionParticipantesView();
        $response->eventoId = $evento->getId();
        $response->participantes = $this->buildParticipantesSeleccionResponse($evento->getId(), $participantes, $evento, $user);
        $response->updatedAt = $this->resolveUpdatedAtFromGranular($seleccionGranular);

        return $response;
    }

    /**
     * @param list<array<string, mixed>> $participantes
     * @return list<array<string, mixed>>
     */
    private function buildParticipantesSeleccionResponse(string $eventoId, array $participantes, Evento $evento, Usuario $user): array
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

                    $inscripcion = $this->inscripcionRepository->findOneByUsuarioAndEvento($usuario->getId(), $eventoId);
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

                $inscripcionInvitado = $this->inscripcionRepository->findOneByInvitadoAndEvento($invitado->getId(), $eventoId);
                $inscripcionRelacion = $this->buildInscripcionRelacion(
                    $inscripcionInvitado,
                    static fn($linea): bool => $linea->getInvitado()?->getId() === $invitado->getId(),
                );

                if ($inscripcionRelacion !== null) {
                    $item['inscripcionRelacion'] = $inscripcionRelacion;
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
            $participantes[] = [
                'id' => $participanteId,
                'origen' => $origen,
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
