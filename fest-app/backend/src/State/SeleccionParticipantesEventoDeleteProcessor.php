<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Inscripcion;
use App\Entity\InscripcionLinea;
use App\Entity\SeleccionParticipanteEvento;
use App\Entity\SeleccionParticipanteEventoLinea;
use App\Entity\Usuario;
use App\Enum\EstadoInscripcionEnum;
use App\Service\PriceCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class SeleccionParticipantesEventoDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly PriceCalculatorService $priceCalculator,
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
            $data->getInscritoPorUsuario()?->getId() !== $user->getId()
            && $data->getEvento()?->getEntidad()?->getId() !== $user->getEntidad()?->getId()
        ) {
            throw new AccessDeniedHttpException('No tienes permiso para eliminar esta selección.');
        }

        $inscripcion = $this->resolveInscripcion($data);
        $lineasInscripcion = $this->getLineasInscripcionDeSeleccion($data, $inscripcion);

        foreach ($lineasInscripcion as $linea) {
            if ($linea->isPagada()) {
                throw new ConflictHttpException(
                    'No se puede eliminar esta selección porque tiene líneas de inscripción pagadas.'
                );
            }
        }

        foreach ($lineasInscripcion as $linea) {
            $inscripcion?->removeLinea($linea);
            $this->entityManager->remove($linea);
        }

        if ($inscripcion !== null) {
            if ($inscripcion->getLineas()->isEmpty()) {
                $this->entityManager->remove($inscripcion);
            } else {
                $inscripcion->setImporteTotal(
                    $this->priceCalculator->calculateTotal(
                        $inscripcion->getLineas()->toArray()
                    )
                );

                $inscripcion->actualizarEstadoPago();

                if (abs($inscripcion->getImporteTotal()) < 0.00001) {
                    $inscripcion->setEstadoInscripcion(
                        EstadoInscripcionEnum::CONFIRMADA
                    );
                }
            }
        }

        foreach ($data->getLineas()->toArray() as $lineaSeleccion) {
            if (!$lineaSeleccion instanceof SeleccionParticipanteEventoLinea) {
                continue;
            }

            $data->removeLinea($lineaSeleccion);
            $this->entityManager->remove($lineaSeleccion);
        }

        $this->entityManager->remove($data);
        $this->entityManager->flush();

        return null;
    }

    /**
     * @return InscripcionLinea[]
     */
    private function getLineasInscripcionDeSeleccion(
        SeleccionParticipanteEvento $seleccion,
        ?Inscripcion $inscripcion,
    ): array {
        if ($inscripcion === null) {
            return [];
        }

        $lineas = [];

        foreach ($inscripcion->getLineas() as $linea) {
            if (!$linea instanceof InscripcionLinea) {
                continue;
            }

            if (!$this->lineaBelongsToSeleccion($linea, $seleccion)) {
                continue;
            }

            $lineas[] = $linea;
        }

        return $lineas;
    }

    private function resolveInscripcion(SeleccionParticipanteEvento $seleccion): ?Inscripcion
    {
        foreach ($seleccion->getEvento()->getInscripciones() as $inscripcion) {
            foreach ($inscripcion->getLineas() as $linea) {
                if (!$linea instanceof InscripcionLinea) {
                    continue;
                }

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
