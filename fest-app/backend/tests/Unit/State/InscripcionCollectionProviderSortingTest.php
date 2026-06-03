<?php

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Evento;
use App\Entity\Inscripcion;
use App\Entity\InscripcionLinea;
use App\Entity\Usuario;
use App\Enum\EstadoInscripcionEnum;
use App\Enum\EstadoLineaInscripcionEnum;
use App\Enum\EstadoPagoEnum;
use App\State\InscripcionCollectionProvider;
use PHPUnit\Framework\TestCase;

final class InscripcionCollectionProviderSortingTest extends TestCase
{
    public function testProvideSortsByEventoFechaAndHoraDescAfterGrouping(): void
    {
        $usuario = $this->createConfiguredMock(Usuario::class, [
            'getId' => 'usuario-1',
            'getFechaBajaCenso' => null,
        ]);

        $eventoAntiguo = $this->createConfiguredMock(Evento::class, [
            'getId' => 'evento-antiguo',
            'getTitulo' => 'Evento antiguo',
            'getDescripcion' => null,
            'getFechaEvento' => new \DateTimeImmutable('2026-06-01 00:00:00'),
            'getHoraInicio' => new \DateTimeImmutable('2026-06-01 12:00:00'),
            'getLugar' => null,
            'getInscripcionAbierta' => false,
            'getFechaFinInscripcion' => null,
        ]);

        $eventoReciente = $this->createConfiguredMock(Evento::class, [
            'getId' => 'evento-reciente',
            'getTitulo' => 'Evento reciente',
            'getDescripcion' => null,
            'getFechaEvento' => new \DateTimeImmutable('2026-06-10 00:00:00'),
            'getHoraInicio' => new \DateTimeImmutable('2026-06-10 21:00:00'),
            'getLugar' => null,
            'getInscripcionAbierta' => false,
            'getFechaFinInscripcion' => null,
        ]);

        $provider = new InscripcionCollectionProvider(new class([
            $this->createInscripcion('ins-1', 'COD-1', $eventoAntiguo, $usuario),
            $this->createInscripcion('ins-2', 'COD-2', $eventoReciente, $usuario),
        ]) implements ProviderInterface {
            public function __construct(private readonly array $items)
            {
            }

            public function provide(\ApiPlatform\Metadata\Operation $operation, array $uriVariables = [], array $context = []): array|null|object
            {
                return $this->items;
            }
        });

        $result = $provider->provide(new GetCollection(), [], [
            'filters' => [
                'pagination' => 'false',
                'order' => [
                    'evento.fechaEvento' => 'desc',
                    'evento.horaInicio' => 'desc',
                ],
            ],
        ]);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame('evento-reciente', $result[0]->evento['id']);
        $this->assertSame('evento-antiguo', $result[1]->evento['id']);
    }

    private function createInscripcion(string $id, string $codigo, Evento $evento, Usuario $usuario): Inscripcion
    {
        $inscripcion = new Inscripcion();
        $this->setPrivateProperty($inscripcion, 'id', $id);
        $inscripcion->setCodigo($codigo);
        $inscripcion->setEvento($evento);
        $inscripcion->setEstadoInscripcion(EstadoInscripcionEnum::PENDIENTE);
        $inscripcion->setEstadoPago(EstadoPagoEnum::PENDIENTE);
        $inscripcion->setImporteTotal(5.0);
        $inscripcion->setImportePagado(0.0);
        $inscripcion->addLinea($this->createLinea('linea-' . $id, $usuario));

        return $inscripcion;
    }

    private function createLinea(string $id, Usuario $usuario): InscripcionLinea
    {
        $linea = new InscripcionLinea();
        $actividad = $this->createConfiguredMock(\App\Entity\ActividadEvento::class, [
            'getId' => 'actividad-' . $id,
        ]);

        $this->setPrivateProperty($linea, 'id', $id);
        $linea->setActividad($actividad);
        $linea->setUsuario($usuario);
        $linea->setPrecioUnitario(5.0);
        $linea->setEstadoLinea(EstadoLineaInscripcionEnum::PENDIENTE);
        $linea->setPagada(false);
        $this->setPrivateProperty($linea, 'nombrePersonaSnapshot', 'Participante');
        $this->setPrivateProperty($linea, 'tipoPersonaSnapshot', 'adulto');
        $this->setPrivateProperty($linea, 'nombreActividadSnapshot', 'Menú');
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

