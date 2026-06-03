<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\ProviderInterface;
use App\Dto\InscripcionCollectionOutput;
use App\Entity\Inscripcion;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class InscripcionCollectionProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private readonly ProviderInterface $collectionProvider,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ArrayPaginator|array
    {
        $filters = is_array($context['filters'] ?? null) ? $context['filters'] : [];
        $order = is_array($filters['order'] ?? null) ? $filters['order'] : [];
        $collectionContext = $context;
        $collectionContext['filters'] = [
            ...$filters,
            'pagination' => 'false',
        ];
        unset($collectionContext['filters']['order']);

        /** @var iterable<Inscripcion> $collection */
        $collection = $this->collectionProvider->provide($operation, $uriVariables, $collectionContext);

        $inscripciones = $collection instanceof \Traversable
            ? iterator_to_array($collection, false)
            : array_values((array) $collection);

        $agrupadas = $this->groupUniqueByEvento($inscripciones);
        $this->sortOutputs($agrupadas, $order);

        $paginationEnabled = $this->isPaginationEnabled($filters);

        if (!$paginationEnabled) {
            return $agrupadas;
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $itemsPerPage = max(1, (int) ($filters['itemsPerPage'] ?? 30));
        $offset = ($page - 1) * $itemsPerPage;

        return new ArrayPaginator($agrupadas, $offset, $itemsPerPage);
    }

    /**
     * @param list<Inscripcion> $inscripciones
     * @return list<array<string, mixed>>
     */
    private function groupUniqueByEvento(array $inscripciones): array
    {
        /** @var array<string, array<string, mixed>> $grouped */
        $grouped = [];

        foreach ($inscripciones as $inscripcion) {
            if (!$inscripcion instanceof Inscripcion) {
                continue;
            }

            $evento = $inscripcion->getEvento();
            $eventoId = (string) ($evento->getId() ?? '');
            if ($eventoId === '') {
                continue;
            }

            if (!isset($grouped[$eventoId])) {
                $grouped[$eventoId] = $this->createOutputFromInscripcion($inscripcion);
                continue;
            }

            $this->mergeInscripciones($grouped[$eventoId], $inscripcion);
        }

        return array_values($grouped);
    }

    private function createOutputFromInscripcion(Inscripcion $inscripcion): array
    {
        $evento = $inscripcion->getEvento();
        $lineas = $this->mapLineas($inscripcion);
        return [
            'id' => (string) $inscripcion->getId(),
            'codigo' => $inscripcion->getCodigo(),
            'evento' => [
                'id' => (string) $evento->getId(),
                'titulo' => $evento->getTitulo(),
                'descripcion' => $evento->getDescripcion(),
                'fechaEvento' => $evento->getFechaEvento()->format('c'),
                'horaInicio' => $evento->getHoraInicio()?->format('c'),
                'lugar' => $evento->getLugar(),
                'inscripcionAbierta' => $evento->getInscripcionAbierta(),
                'fechaLimiteInscripcion' => $evento->getFechaFinInscripcion()?->format('c'),
            ],
            'estadoInscripcion' => $inscripcion->getEstadoInscripcion()->value,
            'estadoPago' => $inscripcion->getEstadoPago()->value,
            'importeTotal' => $inscripcion->getImporteTotal(),
            'importePagado' => $inscripcion->getImportePagado(),
            'moneda' => $inscripcion->getMoneda(),
            'lineas' => $lineas,
            'totalLineas' => count($lineas),
        ];
    }

    private function mergeInscripciones(array &$target, Inscripcion $source): void
    {
        $target['importeTotal'] += $source->getImporteTotal();
        $target['importePagado'] += $source->getImportePagado();
        $target['lineas'] = $this->mergeLineas($target['lineas'], $this->mapLineas($source));
        $target['totalLineas'] = count($target['lineas']);

        if ($this->isEstadoPagoPriorityGreater($source->getEstadoPago()->value, $target['estadoPago'])) {
            $target['estadoPago'] = $source->getEstadoPago()->value;
        }

        if ($this->isEstadoInscripcionPriorityGreater($source->getEstadoInscripcion()->value, $target['estadoInscripcion'])) {
            $target['estadoInscripcion'] = $source->getEstadoInscripcion()->value;
        }
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $order
     */
    private function sortOutputs(array &$items, array $order): void
    {
        $dateDirection = $this->normalizeSortDirection($order['evento.fechaEvento'] ?? null);
        $timeDirection = $this->normalizeSortDirection($order['evento.horaInicio'] ?? null);

        usort($items, function (array $left, array $right) use ($dateDirection, $timeDirection): int {
            $leftDate = (string) ($left['evento']['fechaEvento'] ?? '');
            $rightDate = (string) ($right['evento']['fechaEvento'] ?? '');

            $dateComparison = $leftDate <=> $rightDate;
            if ($dateComparison !== 0) {
                return $dateDirection === 'asc' ? $dateComparison : -$dateComparison;
            }

            $leftTime = (string) ($left['evento']['horaInicio'] ?? '');
            $rightTime = (string) ($right['evento']['horaInicio'] ?? '');

            $timeComparison = $leftTime <=> $rightTime;
            if ($timeComparison !== 0) {
                return $timeDirection === 'asc' ? $timeComparison : -$timeComparison;
            }

            return strcmp((string) $left['id'], (string) $right['id']);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapLineas(Inscripcion $inscripcion): array
    {
        $result = [];

        foreach ($inscripcion->getLineas() as $linea) {
            if ($linea->getEstadoLinea()->value === 'cancelada') {
                continue;
            }

            $usuario = $linea->getUsuario();
            if ($usuario !== null && $usuario->getFechaBajaCenso() !== null) {
                continue;
            }

            $actividad = $linea->getActividad();

            $result[] = [
                'id' => (string) $linea->getId(),
                'actividad' => $actividad?->getId() ? '/api/actividad_eventos/' . $actividad->getId() : null,
                'usuario' => $usuario?->getId() ? '/api/usuarios/' . $usuario->getId() : null,
                'invitado' => $linea->getInvitado()?->getId() ? '/api/invitados/' . $linea->getInvitado()?->getId() : null,
                'nombrePersonaSnapshot' => $linea->getNombrePersonaSnapshot(),
                'tipoPersonaSnapshot' => $linea->getTipoPersonaSnapshot(),
                'nombreActividadSnapshot' => $linea->getNombreActividadSnapshot(),
                'franjaComidaSnapshot' => $linea->getFranjaComidaSnapshot(),
                'precioUnitario' => $linea->getPrecioUnitario(),
                'estadoLinea' => $linea->getEstadoLinea()->value,
                'pagada' => $linea->isPagada(),
            ];
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $existing
     * @param list<array<string, mixed>> $incoming
     * @return list<array<string, mixed>>
     */
    private function mergeLineas(array $existing, array $incoming): array
    {
        $merged = [];

        foreach ([...$existing, ...$incoming] as $linea) {
            $lineaId = is_string($linea['id'] ?? null) ? trim((string) $linea['id']) : '';
            if ($lineaId === '') {
                continue;
            }

            $merged[$lineaId] = $linea;
        }

        return array_values($merged);
    }

    private function isPaginationEnabled(array $filters): bool
    {
        $pagination = $filters['pagination'] ?? null;

        if ($pagination === null) {
            return true;
        }

        if (is_bool($pagination)) {
            return $pagination;
        }

        return !in_array(strtolower((string) $pagination), ['0', 'false', 'off', 'no'], true);
    }

    private function normalizeSortDirection(mixed $direction): string
    {
        return strtolower((string) $direction) === 'asc' ? 'asc' : 'desc';
    }

    private function isEstadoPagoPriorityGreater(string $candidate, string $current): bool
    {
        return $this->estadoPagoPriority($candidate) > $this->estadoPagoPriority($current);
    }

    private function isEstadoInscripcionPriorityGreater(string $candidate, string $current): bool
    {
        return $this->estadoInscripcionPriority($candidate) > $this->estadoInscripcionPriority($current);
    }

    private function estadoPagoPriority(string $estado): int
    {
        return match ($estado) {
            'pagado' => 5,
            'parcial' => 4,
            'pendiente' => 3,
            'no_requiere_pago' => 2,
            'devuelto' => 1,
            'cancelado' => 0,
            default => -1,
        };
    }

    private function estadoInscripcionPriority(string $estado): int
    {
        return match ($estado) {
            'confirmada' => 3,
            'pendiente' => 2,
            'lista_espera' => 1,
            'cancelada' => 0,
            default => -1,
        };
    }
}

