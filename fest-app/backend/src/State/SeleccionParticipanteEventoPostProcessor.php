<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\SeleccionParticipanteEventoInput;
use App\Entity\SeleccionParticipanteEvento;
use App\Entity\Usuario;
use App\Repository\EventoRepository;
use App\Repository\InvitadoRepository;
use App\Repository\UsuarioRepository;
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
        private readonly UsuarioRepository $usuarioRepository,
        private readonly InvitadoRepository $invitadoRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof SeleccionParticipanteEventoInput) {
            throw new BadRequestHttpException('Payload inválido.');
        }

        $user = $this->security->getUser();

        if (!$user instanceof Usuario) {
            throw new AccessDeniedHttpException('No autenticado.');
        }

        // Resolver evento: desde el DTO o desde uriVariables (ruta anidada)
        $eventoId = $data->evento !== null
            ? basename($data->evento)
            : (is_string($uriVariables['eventoId'] ?? null) ? $uriVariables['eventoId'] : null);

        if ($eventoId === null) {
            throw new BadRequestHttpException('Evento no proporcionado.');
        }

        $evento = $this->eventoRepository->find($eventoId);

        if ($evento === null) {
            throw new NotFoundHttpException('Evento no encontrado.');
        }

        if ($evento->getEntidad()->getId() !== $user->getEntidad()->getId()) {
            throw new AccessDeniedHttpException('No tienes acceso a este evento.');
        }

        if (!$evento->estaInscripcionAbierta()) {
            throw new BadRequestHttpException('La inscripción para este evento está cerrada.');
        }

        // Resolver usuario e invitado desde IRIs
        $tieneUsuario = $data->usuario !== null;
        $tieneInvitado = $data->invitado !== null;

        if (!$tieneUsuario && !$tieneInvitado) {
            throw new BadRequestHttpException('Debes indicar un participante.');
        }

        if ($tieneUsuario && $tieneInvitado) {
            throw new BadRequestHttpException('Solo se puede indicar un participante por selección.');
        }

        $usuario = null;
        if ($tieneUsuario) {
            $usuario = $this->usuarioRepository->find(basename($data->usuario));

            if ($usuario === null) {
                throw new NotFoundHttpException('Usuario no encontrado.');
            }

            if ($usuario->getEntidad()->getId() !== $user->getEntidad()->getId()) {
                throw new AccessDeniedHttpException('No tienes acceso a este participante.');
            }
        }

        $invitado = null;
        if ($tieneInvitado) {
            $invitado = $this->invitadoRepository->find(basename($data->invitado));

            if ($invitado === null) {
                throw new NotFoundHttpException('Invitado no encontrado.');
            }

            if ($invitado->getEvento()?->getId() !== $evento->getId()) {
                throw new AccessDeniedHttpException('Este invitado no pertenece al evento indicado.');
            }
        }

        // Construir la entidad
        $seleccion = new SeleccionParticipanteEvento();
        $seleccion->setEvento($evento);
        $seleccion->setInscritoPorUsuario($user);
        $seleccion->setUsuario($usuario);
        $seleccion->setInvitado($invitado);

        $this->entityManager->persist($seleccion);
        $this->entityManager->flush();

        return $seleccion;
    }
}
