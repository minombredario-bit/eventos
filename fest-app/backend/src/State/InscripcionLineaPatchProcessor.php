<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ActividadEvento;
use App\Entity\InscripcionLinea;
use App\Enum\EstadoLineaInscripcionEnum;
use App\Enum\TipoActividadEnum;
use App\Enum\TipoPersonaEnum;
use App\Service\EmailQueueService;
use App\Service\InscripcionService;
use App\Service\PriceCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProcessorInterface<InscripcionLinea, InscripcionLinea>
 */
class InscripcionLineaPatchProcessor implements ProcessorInterface
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
        if (!$actividad->esCompatibleConTipoPersona($tipoPersona)) {
            throw new BadRequestHttpException('La actividad seleccionada no es compatible con el tipo de persona.');
        }

        if ($data->getInvitado() !== null) {
            $precio = $this->calculatePriceForInvitado($actividad);
        } else {
            $usuario = $data->getUsuario();
            if ($usuario === null) {
                throw new BadRequestHttpException('La línea no tiene participante válido.');
            }
            $precio = $this->priceCalculatorService->calculatePrice($usuario, $actividad);
        }

        $data->setPrecioUnitario($precio);
        $data->crearSnapshot();

        $inscripcion->setImporteTotal($inscripcion->calcularImporteTotal());
        $inscripcion->actualizarEstadoPago();

        $this->entityManager->flush();
        $this->emailQueueService->enqueueInscripcionCambio($inscripcion, 'actualizado');

        return $data;
    }

    private function calculatePriceForInvitado(ActividadEvento $actividad): float
    {
        if (!$actividad->isEsDePago()) {
            return 0.0;
        }

        if ($actividad->getTipoActividad() === TipoActividadEnum::INFANTIL) {
            return (float) ($actividad->getPrecioInfantil() ?? $actividad->getPrecioBase());
        }

        return (float) ($actividad->getPrecioAdultoExterno() ?? $actividad->getPrecioBase());
    }
}

