<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\SeleccionParticipantesView;
use App\Entity\Evento;
use App\Entity\Usuario;
use App\Repository\EventoRepository;
use App\Repository\InscripcionRepository;
use App\Repository\InvitadoRepository;
use App\Repository\SeleccionParticipantesEventoRepository;
use App\Repository\UsuarioRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SeleccionParticipantesEventoProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EventoRepository $eventoRepository,
        private readonly SeleccionParticipantesEventoRepository $seleccionParticipantesEventoRepository,
        private readonly UsuarioRepository $usuarioRepository,
        private readonly InscripcionRepository $inscripcionRepository,
        private readonly InvitadoRepository $invitadoRepository,
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

        $seleccion = $this->seleccionParticipantesEventoRepository->findOneByUsuarioAndEvento($user, $evento);

        $response = new SeleccionParticipantesView();
        $response->eventoId = $evento->getId();
        $response->participantes = $seleccion === null
            ? []
            : $this->buildParticipantesSeleccionResponse($evento->getId(), $seleccion->getParticipantes(), $evento, $user);
        $response->updatedAt = $seleccion?->getUpdatedAt()->format('c');

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
            }

            $response[] = $item;
        }

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
}
