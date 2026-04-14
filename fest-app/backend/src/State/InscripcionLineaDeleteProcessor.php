<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\InscripcionLinea;
use App\Service\InscripcionService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProcessorInterface<InscripcionLinea, void>
 */
class InscripcionLineaDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly InscripcionService $inscripcionService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof InscripcionLinea) {
            throw new BadRequestHttpException('Línea de inscripción inválida.');
        }

        // DELETE de línea: eliminación física si cumple reglas (no pagada y evento abierto).
        $this->inscripcionService->cancelarLineaInscripcion($data->getInscripcion(), $data);
    }
}

