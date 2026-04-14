<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\InscripcionLinea;
use App\Entity\Pago;
use App\Service\EmailQueueService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PagoWriteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EmailQueueService $emailQueueService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Pago) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $inscripcion = $data->getInscripcion();
        $pendiente = max(0.0, round($inscripcion->calcularImporteTotal() - $inscripcion->getImportePagado(), 2));

        if ($pendiente <= 0.0) {
            throw new BadRequestHttpException('La inscripción no tiene importe pendiente de pago.');
        }

        // El pago siempre liquida el pendiente completo (no se admite pago parcial manual).
        $data->setImporte($pendiente);

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if ($result instanceof Pago) {
            $importePagadoActual = round($inscripcion->getImportePagado() + $result->getImporte(), 2);
            $importeTotal = round($inscripcion->calcularImporteTotal(), 2);
            $inscripcion->setImportePagado(min($importePagadoActual, $importeTotal));

            foreach ($inscripcion->getLineas() as $linea) {
                if (!$linea instanceof InscripcionLinea) {
                    continue;
                }

                if ($linea->getEstadoLinea()->value === 'cancelada') {
                    continue;
                }

                $linea->setPagada(true);
            }

            $inscripcion->actualizarEstadoPago();
            $this->emailQueueService->enqueueInscripcionCambio($result->getInscripcion(), 'pago');
            $this->entityManager->flush();
        }

        return $result;
    }
}

