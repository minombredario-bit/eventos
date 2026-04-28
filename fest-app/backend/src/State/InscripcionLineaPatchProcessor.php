<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\InscripcionLinea;
use App\Enum\EstadoLineaInscripcionEnum;
use App\Service\EmailQueueService;
use App\Service\InscripcionService;
use App\Service\PriceCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProcessorInterface<InscripcionLinea, InscripcionLinea>
 */
final class InscripcionLineaPatchProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InscripcionService $inscripcionService,
        private readonly PriceCalculatorService $priceCalculatorService,
        private readonly EmailQueueService $emailQueueService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InscripcionLinea
    {
        if (!$data instanceof InscripcionLinea) {
            throw new BadRequestHttpException('Línea de inscripción inválida.');
        }

        $inscripcion = $data->getInscripcion();

        if ($data->getEstadoLinea() === EstadoLineaInscripcionEnum::CANCELADA) {
            $this->inscripcionService->cancelarLineaInscripcion($inscripcion, $data);

            return $data;
        }

        if ($data->isPagada()) {
            throw new BadRequestHttpException('No puedes modificar una línea ya pagada.');
        }

        $actividad = $data->getActividad();

        if ($actividad === null) {
            throw new BadRequestHttpException('Debes indicar una actividad válida. Para no incluir, usa DELETE sobre la línea.');
        }

        if ($actividad->getEvento()->getId() !== $inscripcion->getEvento()->getId()) {
            throw new BadRequestHttpException('La actividad no pertenece a este evento.');
        }

        if (!$actividad->isActivo()) {
            throw new BadRequestHttpException('La actividad seleccionada no está activa.');
        }

        $tipoPersona = $data->getInvitado()?->getTipoPersona() ?? $data->getUsuario()?->getTipoPersona();

        if ($tipoPersona === null) {
            throw new BadRequestHttpException('La línea no tiene tipo de persona válido.');
        }

        if (!$actividad->esCompatibleConTipoPersona($tipoPersona)) {
            throw new BadRequestHttpException('La actividad seleccionada no es compatible con el tipo de persona.');
        }

        $isInvitado = $data->getInvitado() !== null;

        if ($isInvitado && !$actividad->isPermiteInvitados()) {
            throw new BadRequestHttpException('La actividad seleccionada no permite invitados.');
        }

        if (!$isInvitado && $data->getUsuario() === null) {
            throw new BadRequestHttpException('La línea no tiene participante válido.');
        }

        $data->setPrecioUnitario(
            $this->priceCalculatorService->calculatePriceForParticipant(
                $tipoPersona->value,
                $isInvitado,
                $actividad,
            )
        );

        $data->crearSnapshot();

        $inscripcion->setImporteTotal(
            $this->priceCalculatorService->calculateTotal(
                $inscripcion->getLineas()->toArray()
            )
        );

        $inscripcion->actualizarEstadoPago();

        $this->entityManager->flush();
        $this->emailQueueService->enqueueInscripcionCambio($inscripcion, 'actualizado');

        return $data;
    }
}
