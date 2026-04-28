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

final class SeleccionParticipanteEventoPostProcessor implements ProcessorInterface
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

        $user = $this->security->getUser();

        if (!$user instanceof Usuario) {
            throw new AccessDeniedHttpException('No autenticado.');
        }

        $evento = $data->getEvento();

        if ($evento === null) {
            $eventoId = is_string($uriVariables['eventoId'] ?? null)
                ? $uriVariables['eventoId']
                : null;

            if ($eventoId === null) {
                throw new BadRequestHttpException('Evento no proporcionado.');
            }

            $evento = $this->eventoRepository->find($eventoId);

            if ($evento === null) {
                throw new NotFoundHttpException('Evento no encontrado.');
            }

            $data->setEvento($evento);
        }

        if ($evento->getEntidad()->getId() !== $user->getEntidad()->getId()) {
            throw new AccessDeniedHttpException('No tienes acceso a este evento.');
        }

        if (!$evento->estaInscripcionAbierta()) {
            throw new BadRequestHttpException('La inscripción para este evento está cerrada.');
        }

        $data->setInscritoPorUsuario($user);

        $tieneUsuario = $data->getUsuario() !== null;
        $tieneInvitado = $data->getInvitado() !== null;

        if (!$tieneUsuario && !$tieneInvitado) {
            throw new BadRequestHttpException('Debes indicar un participante.');
        }

        if ($tieneUsuario && $tieneInvitado) {
            throw new BadRequestHttpException('Solo se puede indicar un participante por selección.');
        }

        if ($tieneUsuario && $data->getUsuario()->getEntidad()->getId() !== $user->getEntidad()->getId()) {
            throw new AccessDeniedHttpException('No tienes acceso a este participante.');
        }

        if ($tieneInvitado && $data->getInvitado()->getEvento()?->getId() !== $evento->getId()) {
            throw new AccessDeniedHttpException('Este invitado no pertenece al evento indicado.');
        }

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
