<?php
namespace App\Tests\Unit\State;

use App\Entity\Evento;
use App\EventListener\EventoPreRemoveListener;
use App\State\EventoForceDeleteProcessor;
use App\State\EventoCancelProcessor;
use App\Repository\InscripcionRepository;
use App\Repository\PagoRepository;
use PHPUnit\Framework\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\Inscripcion;
use App\Entity\Pago;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EventoDeletionTest extends TestCase
{
    public function testPreRemoveListenerBlocksWhenInscripcionesExist(): void
    {
        $inscripcionRepo = $this->createMock(InscripcionRepository::class);
        $inscripcionRepo->method('count')->willReturn(2);

        $listener = new EventoPreRemoveListener($inscripcionRepo);

        $evento = new Evento();

        $em = $this->createMock(EntityManagerInterface::class);
        // Create a lightweight stub that provides getObject() and getEntityManager()
        $args = new class($evento, $em) {
            private $obj;
            private $em;
            public function __construct($obj, $em) { $this->obj = $obj; $this->em = $em; }
            public function getObject() { return $this->obj; }
            public function getEntityManager() { return $this->em; }
        };

        $this->expectException(ConflictHttpException::class);
        $listener->preRemove($args);
    }

    public function testForceDeleteProcessorDeletesRelatedEntitiesAndDelegates(): void
    {
        $inscripcion = $this->createMock(Inscripcion::class);
        $pago = $this->createMock(Pago::class);

        $inscripcionRepo = $this->createMock(InscripcionRepository::class);
        $inscripcionRepo->method('findBy')->willReturn([$inscripcion]);

        $pagoRepo = $this->createMock(PagoRepository::class);
        $pagoRepo->method('findBy')->willReturn([$pago]);

        $removeProcessor = $this->createMock(ProcessorInterface::class);
        $removeProcessor->expects($this->once())->method('process');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        // expect remove called for pago and for inscripcion (the remove of the evento
        // is delegated to the removeProcessor mock and not performed here)
        $entityManager->expects($this->exactly(2))->method('remove');
        $entityManager->expects($this->once())->method('persist');

        $security = $this->createMock(Security::class);
        $security->method('isGranted')->with('ROLE_SUPERADMIN')->willReturn(true);
        $security->method('getUser')->willReturn(null);

        $processor = new EventoForceDeleteProcessor(
            $removeProcessor,
            $inscripcionRepo,
            $pagoRepo,
            $entityManager,
            $security
        );

        $evento = new Evento();

        // should not throw
        $processor->process($evento, $this->createMock(\ApiPlatform\Metadata\Operation::class), [], []);
    }

    public function testCancelProcessorSetsEstadoAndDelegates(): void
    {
        $persistProcessor = $this->createMock(ProcessorInterface::class);
        $persistProcessor->expects($this->once())->method('process');

        $processor = new EventoCancelProcessor($persistProcessor);

        $evento = new Evento();
        $result = $processor->process($evento, $this->createMock(\ApiPlatform\Metadata\Operation::class), [], []);

        $this->assertSame(\App\Enum\EstadoEventoEnum::CANCELADO, $evento->getEstado());
    }
}

