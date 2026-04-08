<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\InscripcionLinea;
use App\Enum\EstadoLineaInscripcionEnum;
use App\Enum\TipoMenuEnum;
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

        $menu = $data->getMenu();
        if ($menu === null) {
            throw new BadRequestHttpException('Debes indicar un menú válido. Para no incluir, usa DELETE sobre la línea.');
        }
        if ($menu->getEvento()->getId() !== $inscripcion->getEvento()->getId()) {
            throw new BadRequestHttpException('El menú no pertenece a este evento.');
        }

        if (!$menu->isActivo()) {
            throw new BadRequestHttpException('El menú seleccionado no está activo.');
        }

        $tipoPersona = $data->getInvitado()?->getTipoPersona() ?? TipoPersonaEnum::ADULTO;
        if (!$menu->esCompatibleConTipoPersona($tipoPersona)) {
            throw new BadRequestHttpException('El menú seleccionado no es compatible con el tipo de persona.');
        }

        if ($data->getInvitado() !== null) {
            $precio = $this->calculatePriceForInvitado($menu);
        } else {
            $usuario = $data->getUsuario();
            if ($usuario === null) {
                throw new BadRequestHttpException('La línea no tiene participante válido.');
            }
            $precio = $this->priceCalculatorService->calculatePrice($usuario, $menu);
        }

        $data->setPrecioUnitario($precio);
        $data->crearSnapshot();

        $inscripcion->setImporteTotal($inscripcion->calcularImporteTotal());
        $inscripcion->actualizarEstadoPago();

        $this->entityManager->flush();
        $this->emailQueueService->enqueueInscripcionCambio($inscripcion, 'actualizado');

        return $data;
    }

    private function calculatePriceForInvitado(\App\Entity\MenuEvento $menu): float
    {
        if (!$menu->isEsDePago()) {
            return 0.0;
        }

        if ($menu->getTipoMenu() === TipoMenuEnum::INFANTIL) {
            return (float) ($menu->getPrecioInfantil() ?? $menu->getPrecioBase());
        }

        return (float) ($menu->getPrecioAdultoExterno() ?? $menu->getPrecioBase());
    }
}

