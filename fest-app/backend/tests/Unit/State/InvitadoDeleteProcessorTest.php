<?php

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Operation;
use App\Entity\Invitado;
use App\Entity\SeleccionParticipanteEvento;
use App\Repository\InvitadoRepository;
use App\Repository\SeleccionParticipanteEventoRepository;
use App\State\InvitadoDeleteProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class InvitadoDeleteProcessorTest extends TestCase
{
    public function testProcessAppliesLogicalDeleteAndRemovesGranularSelections(): void
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

        $seleccion = (new SeleccionParticipanteEvento())
            ->setEvento($evento)
            ->setInscritoPorUsuario($usuario)
            ->setInvitado($invitado)
            ->setUsuario(null);

        $invitadoRepository = $this->createMock(InvitadoRepository::class);
        $invitadoRepository
            ->expects($this->once())
            ->method('resolveHouseholdUserIds')
            ->with($usuario)
            ->willReturn(['user-1']);

        $seleccionRepository = $this->createMock(SeleccionParticipanteEventoRepository::class);
        $seleccionRepository
            ->expects($this->once())
            ->method('findByEventoAndInvitadoAndInscritoPorUsuarioIds')
            ->with($evento, $invitado, ['user-1'])
            ->willReturn([$seleccion]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($seleccion);
        $entityManager->expects($this->once())->method('flush');

        $processor = new InvitadoDeleteProcessor($entityManager, $invitadoRepository, $seleccionRepository);
        $processor->process($invitado, $this->createMock(Operation::class));

        $this->assertNotNull($invitado->getDeletedAt());
    }

    public function testProcessSkipsWhenAlreadyDeleted(): void
    {
        $invitado = new Invitado();
        $invitado->setDeletedAt(new \DateTimeImmutable('-1 day'));

        $invitadoRepository = $this->createMock(InvitadoRepository::class);
        $invitadoRepository->expects($this->never())->method('resolveHouseholdUserIds');

        $seleccionRepository = $this->createMock(SeleccionParticipanteEventoRepository::class);
        $seleccionRepository->expects($this->never())->method('findByEventoAndInvitadoAndInscritoPorUsuarioIds');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $processor = new InvitadoDeleteProcessor($entityManager, $invitadoRepository, $seleccionRepository);
        $processor->process($invitado, $this->createMock(Operation::class));

        $this->assertTrue($invitado->isDeleted());
    }
}
