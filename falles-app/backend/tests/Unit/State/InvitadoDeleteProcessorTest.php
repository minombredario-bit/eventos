<?php

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Operation;
use App\Entity\SeleccionParticipantesEvento;
use App\Entity\Invitado;
use App\Repository\InvitadoRepository;
use App\Repository\SeleccionParticipantesEventoRepository;
use App\State\InvitadoDeleteProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class InvitadoDeleteProcessorTest extends TestCase
{
    public function testProcessAppliesLogicalDeleteAndFlushes(): void
    {
        $invitado = new Invitado();
        $invitado->setDeletedAt(null);

        $usuario = $this->createConfiguredMock(\App\Entity\Usuario::class, [
            'getId' => 'user-1',
        ]);
        $evento = $this->createConfiguredMock(\App\Entity\Evento::class, [
            'getId' => 'evento-1',
        ]);

        $invitado
            ->setCreadoPor($usuario)
            ->setEvento($evento)
            ->setNombre('Ana')
            ->setApellidos('Invitada');

        $seleccion = (new SeleccionParticipantesEvento())
            ->setUsuario($usuario)
            ->setEvento($evento)
            ->setParticipantes([
                ['id' => (string) $invitado->getId(), 'origen' => 'invitado'],
                ['id' => 'fam-1', 'origen' => 'familiar'],
            ]);

        $invitadoRepository = $this->createMock(InvitadoRepository::class);
        $invitadoRepository
            ->expects($this->once())
            ->method('resolveHouseholdUserIds')
            ->with($usuario)
            ->willReturn(['user-1']);

        $seleccionRepository = $this->createMock(SeleccionParticipantesEventoRepository::class);
        $seleccionRepository
            ->expects($this->once())
            ->method('findByUsuarioIdsAndEvento')
            ->with(['user-1'], $evento)
            ->willReturn([$seleccion]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $processor = new InvitadoDeleteProcessor($entityManager, $invitadoRepository, $seleccionRepository);
        $processor->process($invitado, $this->createMock(Operation::class));

        $this->assertNotNull($invitado->getDeletedAt());
        $this->assertSame([
            ['id' => 'fam-1', 'origen' => 'familiar'],
        ], $seleccion->getParticipantes());
    }

    public function testProcessSkipsWhenAlreadyDeleted(): void
    {
        $invitado = new Invitado();
        $invitado->setDeletedAt(new \DateTimeImmutable('-1 day'));

        $invitadoRepository = $this->createMock(InvitadoRepository::class);
        $invitadoRepository->expects($this->never())->method('resolveHouseholdUserIds');

        $seleccionRepository = $this->createMock(SeleccionParticipantesEventoRepository::class);
        $seleccionRepository->expects($this->never())->method('findByUsuarioIdsAndEvento');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $processor = new InvitadoDeleteProcessor($entityManager, $invitadoRepository, $seleccionRepository);
        $processor->process($invitado, $this->createMock(Operation::class));

        $this->assertTrue($invitado->isDeleted());
    }

    public function testProcessRemovesDeletedInvitadoFromAllHouseholdSelections(): void
    {
        $invitado = new Invitado();

        $usuarioCreador = $this->createConfiguredMock(\App\Entity\Usuario::class, [
            'getId' => 'user-1',
        ]);
        $evento = $this->createConfiguredMock(\App\Entity\Evento::class, [
            'getId' => 'evento-1',
        ]);

        $invitado
            ->setCreadoPor($usuarioCreador)
            ->setEvento($evento)
            ->setNombre('Ana')
            ->setApellidos('Invitada');

        $seleccionTitular = (new SeleccionParticipantesEvento())
            ->setUsuario($usuarioCreador)
            ->setEvento($evento)
            ->setParticipantes([
                ['id' => (string) $invitado->getId(), 'origen' => 'invitado'],
                ['id' => 'fam-1', 'origen' => 'familiar'],
            ]);

        $usuarioRelacionado = $this->createConfiguredMock(\App\Entity\Usuario::class, [
            'getId' => 'user-2',
        ]);
        $seleccionRelacionado = (new SeleccionParticipantesEvento())
            ->setUsuario($usuarioRelacionado)
            ->setEvento($evento)
            ->setParticipantes([
                ['id' => 'legacy', 'origen' => 'invitado'],
                ['id' => (string) $invitado->getId(), 'origen' => 'invitado'],
            ]);

        $invitadoRepository = $this->createMock(InvitadoRepository::class);
        $invitadoRepository
            ->expects($this->once())
            ->method('resolveHouseholdUserIds')
            ->with($usuarioCreador)
            ->willReturn(['user-1', 'user-2']);

        $seleccionRepository = $this->createMock(SeleccionParticipantesEventoRepository::class);
        $seleccionRepository
            ->expects($this->once())
            ->method('findByUsuarioIdsAndEvento')
            ->with(['user-1', 'user-2'], $evento)
            ->willReturn([$seleccionTitular, $seleccionRelacionado]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $processor = new InvitadoDeleteProcessor($entityManager, $invitadoRepository, $seleccionRepository);
        $processor->process($invitado, $this->createMock(Operation::class));

        $this->assertSame([
            ['id' => 'fam-1', 'origen' => 'familiar'],
        ], $seleccionTitular->getParticipantes());

        $this->assertSame([
            ['id' => 'legacy', 'origen' => 'invitado'],
        ], $seleccionRelacionado->getParticipantes());
    }
}
