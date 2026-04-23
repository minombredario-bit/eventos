<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\SeleccionParticipanteEvento;
use App\Entity\Usuario;
use App\Repository\EventoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SeleccionParticipanteEventoPostProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EventoRepository $eventoRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof SeleccionParticipanteEvento) {
            return $data;
        }

        /** @var Usuario $user */
        $user = $this->security->getUser();
        if (!$user instanceof Usuario) {
            throw new AccessDeniedHttpException('No autenticado.');
        }

        // ── 1. Resolver Evento ────────────────────────────────────────────
        // API Platform hidrata getEvento() cuando el cliente envía el IRI.
        // Para la ruta anidada /eventos/{eventoId}/seleccion_participantes
        // leemos el id de uriVariables como fallback.
        $evento = $data->getEvento() ?? null;
        if ($evento === null) {
            $eventoId = is_string($uriVariables['eventoId'] ?? null) ? $uriVariables['eventoId'] : null;
            if ($eventoId === null) {
                throw new BadRequestHttpException('Evento no proporcionado.');
            }

            $evento = $this->eventoRepository->find($eventoId);
            if ($evento === null) {
                throw new NotFoundHttpException('Evento no encontrado.');
            }

            $data->setEvento($evento);
        }

        // ── 2. Verificar entidad ──────────────────────────────────────────
        if ($evento->getEntidad()->getId() !== $user->getEntidad()->getId()) {
            throw new AccessDeniedHttpException('No tienes acceso a este evento.');
        }

        // ── 3. Verificar inscripción abierta ──────────────────────────────
        if (!$evento->estaInscripcionAbierta()) {
            throw new BadRequestHttpException('La inscripción para este evento está cerrada.');
        }

        // ── 4. Forzar inscritoPorUsuario al usuario autenticado ───────────
        $data->setInscritoPorUsuario($user);

        // ── 5. Validar participante único ─────────────────────────────────
        // API Platform hidrata getUsuario() / getInvitado() desde el IRI enviado
        // en el cuerpo antes de llamar al processor.
        $tieneUsuario  = $data->getUsuario() !== null;
        $tieneInvitado = $data->getInvitado() !== null;

        if (!$tieneUsuario && !$tieneInvitado) {
            throw new BadRequestHttpException('Debes indicar un participante (usuario o invitado).');
        }

        if ($tieneUsuario && $tieneInvitado) {
            throw new BadRequestHttpException('Solo se puede indicar un participante por selección.');
        }

        // ── 6. Verificar pertenencia del participante ─────────────────────
        if ($tieneUsuario) {
            if ($data->getUsuario()->getEntidad()->getId() !== $user->getEntidad()->getId()) {
                throw new AccessDeniedHttpException('No tienes acceso a este participante.');
            }
        }

        if ($tieneInvitado) {
            if ($data->getInvitado()->getEvento()?->getId() !== $evento->getId()) {
                throw new AccessDeniedHttpException('Este invitado no pertenece al evento indicado.');
            }
        }

        // ── 7. Persistir ──────────────────────────────────────────────────
        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
