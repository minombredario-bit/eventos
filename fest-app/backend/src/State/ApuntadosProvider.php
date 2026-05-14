<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\ProviderInterface;
use App\Dto\ApuntadoOutput;
use App\Dto\ApuntadosPageOutput;
use App\Entity\Evento;
use App\Entity\SeleccionParticipanteEvento;
use App\Entity\Usuario;
use App\Repository\EventoRepository;
use App\Repository\InscripcionRepository;
use App\Repository\InvitadoRepository;
use App\Repository\SeleccionParticipanteEventoRepository;
use App\Repository\UsuarioRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ApuntadosProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EventoRepository $eventoRepository,
        private readonly InvitadoRepository $invitadoRepository,
        private readonly SeleccionParticipanteEventoRepository $seleccionParticipanteEventoRepository,
        private readonly InscripcionRepository $inscripcionRepository,
        private readonly UsuarioRepository $usuarioRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var Usuario $user */
        $user = $this->security->getUser();

        $eventoId = $uriVariables['id'] ?? null;

        if (!$eventoId) {
            throw new NotFoundHttpException('Evento no encontrado.');
        }

        /** @var Evento|null $evento */
        $evento = $this->eventoRepository->find($eventoId);

        if (!$evento) {
            throw new NotFoundHttpException('Evento no encontrado.');
        }

        if ($evento->getEntidad()->getId() !== $user->getEntidad()->getId()) {
            throw new AccessDeniedHttpException('No tienes acceso a este evento.');
        }

        $filters = $context['filters'] ?? [];

        $search = isset($filters['nombreCompleto']) && $filters['nombreCompleto'] !== ''
            ? (string) $filters['nombreCompleto']
            : null;

        $page = max(1, (int)($filters['page'] ?? 1));
        $itemsPerPage = max(1, (int)($filters['itemsPerPage'] ?? 10));

        $member = $this->buildApuntados($evento, $user, $search);

        // Ordenación
        $orderNombre = $filters['order']['nombreCompleto'] ?? 'asc';

        usort(
            $member,
            static function (ApuntadoOutput $a, ApuntadoOutput $b) use ($orderNombre): int {
                $result = strcasecmp($a->nombreCompleto, $b->nombreCompleto);

                return strtolower($orderNombre) === 'desc'
                    ? -$result
                    : $result;
            }
        );

        $totalItems = count($member);

        $offset = ($page - 1) * $itemsPerPage;

        $slice = array_slice($member, $offset, $itemsPerPage);

        $lastPage = max(1, (int) ceil($totalItems / $itemsPerPage));

        return new ApuntadosPageOutput(
            evento: [
                'id' => $evento->getId(),
                'titulo' => $evento->getTitulo(),
                'fechaEvento' => $evento->getFechaEvento()->format('Y-m-d'),
            ],
            member: $slice,
            totalItems: $totalItems,
            itemsPerPage: $itemsPerPage,
            currentPage: $page,
            lastPage: $lastPage,
        );
    }

    /**
     * @return ApuntadoOutput[]
     */
    private function buildApuntados(Evento $evento, Usuario $user, ?string $search = null): array
    {
        $selecciones = $this->seleccionParticipanteEventoRepository
            ->findByEvento($evento);

        $seenParticipantes = [];
        $member = [];

        foreach ($selecciones as $seleccion) {
            if (!$seleccion instanceof SeleccionParticipanteEvento) {
                continue;
            }

            $origen = $seleccion->getInvitado() !== null ? 'invitado' : 'familiar';

            $participanteId = $origen === 'invitado'
                ? (string)$seleccion->getInvitado()?->getId()
                : (string)$seleccion->getUsuario()?->getId();

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
                $usuario = $this->usuarioRepository->find($participanteId);

                if ($usuario === null) {
                    continue;
                }

                $nombreCompleto = $usuario->getNombreCompleto() !== ''
                    ? $usuario->getNombreCompleto()
                    : trim($usuario->getNombre() . ' ' . $usuario->getApellidos());

                $inscripcion = $this->inscripcionRepository
                    ->findOneByUsuarioParticipanteAndEvento($participanteId, $evento->getId());

                if ($inscripcion !== null) {
                    $inscripcionId = (string)$inscripcion->getId();
                    $opciones = $this->extractUniqueActividadOptionsByUsuario(
                        $inscripcion->getLineas()->toArray(),
                        $participanteId,
                        null
                    );
                }
            } else {
                $invitado = $this->invitadoRepository->find($participanteId);

                if ($invitado === null) {
                    continue;
                }

                $nombreCompleto = trim(
                    $invitado->getNombre() . ' ' . $invitado->getApellidos()
                );

                $inscripcion = $this->inscripcionRepository
                    ->findOneByInvitadoAndEvento($participanteId, $evento->getId());

                if ($inscripcion !== null) {
                    $inscripcionId = (string)$inscripcion->getId();
                    $opciones = $this->extractUniqueActividadOptionsByUsuario(
                        $inscripcion->getLineas()->toArray(),
                        null,
                        $participanteId
                    );
                }
            }

            if ($nombreCompleto === '') {
                continue;
            }

            if (
                $search !== null &&
                !str_contains(
                    mb_strtolower($nombreCompleto),
                    mb_strtolower(trim($search))
                )
            ) {
                continue;
            }

            $member[] = new ApuntadoOutput(
                $inscripcionId,
                $nombreCompleto,
                $opciones
            );

            $seenParticipantes[$participantKey] = true;
        }

        return $member;
    }

    private function extractUniqueActividadOptionsByUsuario(
        array $lineas,
        ?string $usuarioId,
        ?string $invitadoId
    ): array {
        $opciones = [];

        foreach ($lineas as $linea) {
            if ($linea->getEstadoLinea()->value === 'cancelada') {
                continue;
            }

            // Filtrar por participante concreto
            if ($usuarioId !== null) {
                if ($linea->getUsuario()?->getId() !== $usuarioId) {
                    continue;
                }
            } elseif ($invitadoId !== null) {
                if ($linea->getInvitado()?->getId() !== $invitadoId) {
                    continue;
                }
            }

            $opcion = trim($linea->getNombreActividadSnapshot());

            if ($opcion !== '') {
                $opciones[$opcion] = true;
            }
        }

        return array_keys($opciones);
    }

    private function extractUniqueActividadOptions(array $lineas): array
    {
        $opciones = [];

        foreach ($lineas as $linea) {

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
}
