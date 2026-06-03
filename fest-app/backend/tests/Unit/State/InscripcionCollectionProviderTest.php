<?php

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\ProviderInterface;
use App\Dto\InscripcionCollectionOutput;
use App\Entity\Evento;
use App\Entity\Inscripcion;
use App\Entity\InscripcionLinea;
use App\Entity\Usuario;
use App\Enum\EstadoInscripcionEnum;
use App\Enum\EstadoLineaInscripcionEnum;
use App\Enum\EstadoPagoEnum;
use App\State\InscripcionCollectionProvider;
use PHPUnit\Framework\TestCase;
final class InscripcionCollectionProviderTest extends TestCase
{
    public function testProvideGroupsCollectionByEvento(): void
    {
        $evento = $this->createConfiguredMock(Evento::class, [
            'getId' => 'evento-1',
            'getTitulo' => 'Cena de gala',
            'getDescripcion' => 'Evento anual',
            'getFechaEvento' => new \DateTimeImmutable('2026-06-10 00:00:00'),
            'getHoraInicio' => new \DateTimeImmutable('2026-06-10 21:30:00'),
            'getLugar' => 'Casal',
            'getInscripcionAbierta' => false,
            'getFechaFinInscripcion' => new \DateTimeImmutable('2026-06-08 23:59:59'),
        ]);

        $usuarioTitular = $this->createConfiguredMock(Usuario::class, [
            'getId' => 'usuario-1',
            'getFechaBajaCenso' => null,
        ]);

        $linea1 = $this->createLinea('linea-1', $usuarioTitular, 10.50, 'Menú A');
        $linea2 = $this->createLinea('linea-2', $usuarioTitular, 7.25, 'Menú infantil');

        $inscripcion1 = $this->createInscripcion(
            id: 'inscripcion-1',
            codigo: 'INS-001',
            evento: $evento,
            estadoInscripcion: EstadoInscripcionEnum::PENDIENTE,
            estadoPago: EstadoPagoEnum::PENDIENTE,
            importeTotal: 10.50,
            importePagado: 0.00,
            lineas: [$linea1],
        );

        $inscripcion2 = $this->createInscripcion(
            id: 'inscripcion-2',
            codigo: 'INS-002',
            evento: $evento,
            estadoInscripcion: EstadoInscripcionEnum::CONFIRMADA,
            estadoPago: EstadoPagoEnum::PARCIAL,
            importeTotal: 7.25,
            importePagado: 3.00,
            lineas: [$linea2],
        );

        $innerProvider = new class([$inscripcion1, $inscripcion2]) implements ProviderInterface {
            public function __construct(private readonly array $items)
            {
            }

            public function provide(\ApiPlatform\Metadata\Operation $operation, array $uriVariables = [], array $context = []): array|null|object
            {
                return $this->items;
            }
        };

        $provider = new InscripcionCollectionProvider($innerProvider);

        $result = $provider->provide(new GetCollection(), [], [
            'filters' => [
                'pagination' => 'false',
            ],
        ]);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(InscripcionCollectionOutput::class, $result[0]);
        $this->assertSame('evento-1', $result[0]->evento['id']);
        $this->assertSame(17.75, $result[0]->importeTotal);
        $this->assertSame(3.0, $result[0]->importePagado);
        $this->assertSame('parcial', $result[0]->estadoPago);
        $this->assertSame('confirmada', $result[0]->estadoInscripcion);
        $this->assertCount(2, $result[0]->lineas);
        $this->assertSame(2, $result[0]->totalLineas);
    }

    public function testProvideReturnsPaginatorWhenPaginationIsEnabled(): void
    {
        $eventoA = $this->createConfiguredMock(Evento::class, [
            'getId' => 'evento-a',
            'getTitulo' => 'Evento A',
            'getDescripcion' => null,
            'getFechaEvento' => new \DateTimeImmutable('2026-06-10 00:00:00'),
            'getHoraInicio' => null,
            'getLugar' => null,
            'getInscripcionAbierta' => true,
            'getFechaFinInscripcion' => null,
        ]);
        $eventoB = $this->createConfiguredMock(Evento::class, [
            'getId' => 'evento-b',
            'getTitulo' => 'Evento B',
            'getDescripcion' => null,
            'getFechaEvento' => new \DateTimeImmutable('2026-06-11 00:00:00'),
            'getHoraInicio' => null,
            'getLugar' => null,
            'getInscripcionAbierta' => true,
            'getFechaFinInscripcion' => null,
        ]);

        $usuario = $this->createConfiguredMock(Usuario::class, [
            'getId' => 'usuario-1',
            'getFechaBajaCenso' => null,
        ]);

        $innerProvider = new class([
            $this->createInscripcion('i-a', 'A', $eventoA, EstadoInscripcionEnum::PENDIENTE, EstadoPagoEnum::PENDIENTE, 5.0, 0.0, [$this->createLinea('l-a', $usuario, 5.0, 'A')]),
            $this->createInscripcion('i-b', 'B', $eventoB, EstadoInscripcionEnum::PENDIENTE, EstadoPagoEnum::PENDIENTE, 6.0, 0.0, [$this->createLinea('l-b', $usuario, 6.0, 'B')]),
        ]) implements ProviderInterface {
            public function __construct(private readonly array $items)
            {
            }

            public function provide(\ApiPlatform\Metadata\Operation $operation, array $uriVariables = [], array $context = []): array|null|object
            {
                return $this->items;
            }
        };

        $provider = new InscripcionCollectionProvider($innerProvider);

        $result = $provider->provide(new GetCollection(), [], [
            'filters' => [
                'page' => 2,
                'itemsPerPage' => 1,
            ],
        ]);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
        $items = iterator_to_array($result->getIterator(), false);
        $this->assertCount(1, $items);
        $this->assertSame(2.0, $result->getCurrentPage());
        $this->assertSame(2.0, $result->getLastPage());
        $this->assertSame(2.0, $result->getTotalItems());
    }

    private function createInscripcion(
        string $id,
        string $codigo,
        Evento $evento,
        EstadoInscripcionEnum $estadoInscripcion,
        EstadoPagoEnum $estadoPago,
        float $importeTotal,
        float $importePagado,
        array $lineas,
    ): Inscripcion {
        $inscripcion = new Inscripcion();
        $this->setPrivateProperty($inscripcion, 'id', $id);
        $inscripcion->setCodigo($codigo);
        $inscripcion->setEvento($evento);
        $inscripcion->setEstadoInscripcion($estadoInscripcion);
        $inscripcion->setEstadoPago($estadoPago);
        $inscripcion->setImporteTotal($importeTotal);
        $inscripcion->setImportePagado($importePagado);

        foreach ($lineas as $linea) {
            $inscripcion->addLinea($linea);
        }

        return $inscripcion;
    }

    private function createLinea(string $id, Usuario $usuario, float $precio, string $actividadNombre): InscripcionLinea
    {
        $linea = new InscripcionLinea();
        $actividad = $this->createConfiguredMock(\App\Entity\ActividadEvento::class, [
            'getId' => 'actividad-' . $id,
        ]);

        $this->setPrivateProperty($linea, 'id', $id);
        $linea->setActividad($actividad);
        $linea->setUsuario($usuario);
        $linea->setPrecioUnitario($precio);
        $linea->setEstadoLinea(EstadoLineaInscripcionEnum::PENDIENTE);
        $linea->setPagada(false);
        $this->setPrivateProperty($linea, 'nombrePersonaSnapshot', 'Participante');
        $this->setPrivateProperty($linea, 'tipoPersonaSnapshot', 'adulto');
        $this->setPrivateProperty($linea, 'nombreActividadSnapshot', $actividadNombre);
        $this->setPrivateProperty($linea, 'franjaComidaSnapshot', 'comida');

        return $linea;
    }

    private function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setValue($object, $value);
    }
}

