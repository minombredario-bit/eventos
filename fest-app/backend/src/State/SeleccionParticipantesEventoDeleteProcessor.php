<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Inscripcion;
use App\Entity\InscripcionLinea;
use App\Entity\SeleccionParticipanteEvento;
use App\Entity\Usuario;
use App\Enum\EstadoLineaInscripcionEnum;
use App\Service\InscripcionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SeleccionParticipantesEventoDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly InscripcionService $inscripcionService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof SeleccionParticipanteEvento) {
            return null;
        }

        $user = $this->security->getUser();
        if (!$user instanceof Usuario) {
            throw new AccessDeniedHttpException('No autenticado.');
        }

        if (
            $data->getInscritoPorUsuario()->getId() !== $user->getId()
            && $data->getEvento()->getEntidad()->getId() !== $user->getEntidad()->getId()
        ) {
            throw new AccessDeniedHttpException('No tienes permiso para eliminar esta selección.');
        }

        $inscripcion = $this->resolveInscripcion($data);

        if ($inscripcion !== null) {
            foreach ($inscripcion->getLineas() as $linea) {
                if ($linea->getEstadoLinea() === EstadoLineaInscripcionEnum::CANCELADA) {
                    continue;
                }

                if (!$this->lineaBelongsToSeleccion($linea, $data)) {
                    continue;
                }

                if ($linea->isPagada()) {
                    throw new ConflictHttpException(
                        'No se puede eliminar esta selección porque tiene líneas de inscripción pagadas.'
                    );
                }
            }

            $errors = [];

            foreach ($inscripcion->getLineas()->toArray() as $linea) {
                if (!$linea instanceof InscripcionLinea) {
                    continue;
                }

                if ($linea->getEstadoLinea() === EstadoLineaInscripcionEnum::CANCELADA) {
                    continue;
                }

                if (!$this->lineaBelongsToSeleccion($linea, $data)) {
                    continue;
                }

                try {
                    $this->inscripcionService->cancelarLineaInscripcion($inscripcion, $linea);
                } catch (\Throwable $e) {
                    $errors[] = $e->getMessage();
                }
            }

            if ($errors !== []) {
                throw new ConflictHttpException('No se pudo eliminar la selección: ' . implode(' | ', $errors));
            }

            $this->entityManager->refresh($inscripcion);

            if ($inscripcion->getLineas()->isEmpty()) {
                $this->entityManager->remove($inscripcion);
            }
        }

        $this->entityManager->remove($data);
        $this->entityManager->flush();

        return null;
    }

    private function resolveInscripcion(SeleccionParticipanteEvento $seleccion): ?Inscripcion
    {
        foreach ($seleccion->getEvento()->getInscripciones() as $inscripcion) {
            foreach ($inscripcion->getLineas() as $linea) {
                if ($this->lineaBelongsToSeleccion($linea, $seleccion)) {
                    return $inscripcion;
                }
            }
        }

        return null;
    }

    private function lineaBelongsToSeleccion(
        InscripcionLinea $linea,
        SeleccionParticipanteEvento $seleccion,
    ): bool {
        if ($seleccion->getUsuario() !== null) {
            return $linea->getUsuario()?->getId() === $seleccion->getUsuario()->getId();
        }

        if ($seleccion->getInvitado() !== null) {
            return $linea->getInvitado()?->getId() === $seleccion->getInvitado()->getId();
        }

        return false;
    }
}
