<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

final class MesNacimientoFilter extends AbstractFilter
{
    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        if (!in_array($property, ['mesNacimientoDesde', 'mesNacimientoHasta'], true)) {
            return;
        }

        $filters = $context['filters'] ?? [];

        $mesDesde = $filters['mesNacimientoDesde'] ?? null;
        $mesHasta = $filters['mesNacimientoHasta'] ?? null;

        if (($mesDesde === null || $mesDesde === '') && ($mesHasta === null || $mesHasta === '')) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];

        if ($mesDesde !== null && $mesDesde !== '' && $mesHasta !== null && $mesHasta !== '') {
            $desde = (int) $mesDesde;
            $hasta = (int) $mesHasta;

            if ($desde <= $hasta) {
                $queryBuilder
                    ->andWhere("MONTH($alias.fechaNacimiento) BETWEEN :mesNacimientoDesde AND :mesNacimientoHasta")
                    ->setParameter('mesNacimientoDesde', $desde)
                    ->setParameter('mesNacimientoHasta', $hasta);
            } else {
                $queryBuilder
                    ->andWhere("(MONTH($alias.fechaNacimiento) >= :mesNacimientoDesde OR MONTH($alias.fechaNacimiento) <= :mesNacimientoHasta)")
                    ->setParameter('mesNacimientoDesde', $desde)
                    ->setParameter('mesNacimientoHasta', $hasta);
            }

            return;
        }

        if ($mesDesde !== null && $mesDesde !== '') {
            $queryBuilder
                ->andWhere("MONTH($alias.fechaNacimiento) >= :mesNacimientoDesde")
                ->setParameter('mesNacimientoDesde', (int) $mesDesde);
        }

        if ($mesHasta !== null && $mesHasta !== '') {
            $queryBuilder
                ->andWhere("MONTH($alias.fechaNacimiento) <= :mesNacimientoHasta")
                ->setParameter('mesNacimientoHasta', (int) $mesHasta);
        }
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'mesNacimientoDesde' => [
                'property' => 'fechaNacimiento',
                'type' => 'string',
                'required' => false,
                'description' => 'Mes de nacimiento desde. Formato 01-12.',
            ],
            'mesNacimientoHasta' => [
                'property' => 'fechaNacimiento',
                'type' => 'string',
                'required' => false,
                'description' => 'Mes de nacimiento hasta. Formato 01-12.',
            ],
        ];
    }
}
