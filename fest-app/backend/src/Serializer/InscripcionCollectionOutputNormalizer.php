<?php

namespace App\Serializer;

use App\Dto\InscripcionCollectionOutput;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class InscripcionCollectionOutputNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    /**
     * @param InscripcionCollectionOutput $object
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): \ArrayObject|array|string|int|float|bool|null
    {
        if (!$object instanceof InscripcionCollectionOutput) {
            return $object;
        }

        // Si implementa JsonSerializable, usamos eso
        if ($object instanceof \JsonSerializable) {
            return $object->jsonSerialize();
        }

        // Fallback manual
        return [
            'id' => $object->id,
            'codigo' => $object->codigo,
            'evento' => $object->evento,
            'estadoInscripcion' => $object->estadoInscripcion,
            'estadoPago' => $object->estadoPago,
            'importeTotal' => $object->importeTotal,
            'importePagado' => $object->importePagado,
            'moneda' => $object->moneda,
            'totalLineas' => $object->totalLineas,
            'lineas' => $object->lineas,
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof InscripcionCollectionOutput;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            InscripcionCollectionOutput::class => true,
        ];
    }
}

