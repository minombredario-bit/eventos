<?php

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Operation;
use App\Entity\Evento;
use App\Entity\Invitado;
use App\Entity\Usuario;
use App\Enum\TipoPersonaEnum;
use App\Repository\InvitadoRepository;
use App\State\InvitadoPostProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class InvitadoPostProcessorTest extends TestCase
{
    public function testProcessRejectsDuplicateAgainstHouseholdUsers(): void
    {
        $creador = $this->createUser('José', 'Pérez');

        $evento = new Evento();
        $invitado = (new Invitado())
            ->setCreadoPor($creador)
            ->setEvento($evento)
            ->setNombre(' Jose ')
            ->setApellidos('  Perez ')
            ->setTipoPersona(TipoPersonaEnum::ADULTO);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $invitadoRepository = $this->createMock(InvitadoRepository::class);
        $invitadoRepository
            ->expects($this->never())
            ->method('existsActiveByEventoAndHouseholdAndNormalizedName');

        $processor = new InvitadoPostProcessor($entityManager, $invitadoRepository);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Ya existe una persona del núcleo familiar con ese nombre completo.');

        $processor->process($invitado, $this->createMock(Operation::class));
    }

    public function testProcessRejectsDuplicateAgainstActiveHouseholdInvitados(): void
    {
        $creador = $this->createUser('Carlos', 'Sánchez');
        $evento = new Evento();
        $invitado = (new Invitado())
            ->setCreadoPor($creador)
            ->setEvento($evento)
            ->setNombre('Ána')
            ->setApellidos('Invitada')
            ->setTipoPersona(TipoPersonaEnum::ADULTO);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $invitadoRepository = $this->createMock(InvitadoRepository::class);
        $invitadoRepository
            ->expects($this->once())
            ->method('existsActiveByEventoAndHouseholdAndNormalizedName')
            ->with($evento, $creador, InvitadoRepository::normalizeName('Ána', 'Invitada'))
            ->willReturn(true);
        $invitadoRepository
            ->expects($this->never())
            ->method('findDeletedByEventoAndHouseholdAndNormalizedName');

        $processor = new InvitadoPostProcessor($entityManager, $invitadoRepository);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Ya existe un invitado activo con ese nombre completo en tu núcleo familiar para este evento.');

        $processor->process($invitado, $this->createMock(Operation::class));
    }

    public function testProcessPersistsWhenNoDuplicatesExist(): void
    {
        $creador = $this->createUser('Carlos', 'Sánchez');
        $evento = new Evento();
        $invitado = (new Invitado())
            ->setCreadoPor($creador)
            ->setEvento($evento)
            ->setNombre('Laura')
            ->setApellidos('Mora')
            ->setTipoPersona(TipoPersonaEnum::ADULTO);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($invitado);
        $entityManager->expects($this->once())->method('flush');

        $invitadoRepository = $this->createMock(InvitadoRepository::class);
        $invitadoRepository
            ->expects($this->once())
            ->method('existsActiveByEventoAndHouseholdAndNormalizedName')
            ->willReturn(false);
        $invitadoRepository
            ->expects($this->once())
            ->method('findDeletedByEventoAndHouseholdAndNormalizedName')
            ->willReturn(null);

        $processor = new InvitadoPostProcessor($entityManager, $invitadoRepository);

        $result = $processor->process($invitado, $this->createMock(Operation::class));

        $this->assertSame($invitado, $result);
    }

    public function testProcessReactivatesDeletedInvitadoWithSameNormalizedName(): void
    {
        $creador = $this->createUser('Carlos', 'Sánchez');
        $evento = new Evento();

        $nuevoInvitado = (new Invitado())
            ->setCreadoPor($creador)
            ->setEvento($evento)
            ->setNombre('Ána')
            ->setApellidos('Invitada')
            ->setTipoPersona(TipoPersonaEnum::INFANTIL)
            ->setObservaciones('Observación nueva');

        $invitadoBorrado = (new Invitado())
            ->setCreadoPor($creador)
            ->setEvento($evento)
            ->setNombre('Ana')
            ->setApellidos('Invitada')
            ->setTipoPersona(TipoPersonaEnum::ADULTO)
            ->setObservaciones('Observación anterior')
            ->setDeletedAt(new \DateTimeImmutable('-2 days'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $normalizedName = InvitadoRepository::normalizeName('Ána', 'Invitada');
        $invitadoRepository = $this->createMock(InvitadoRepository::class);
        $invitadoRepository
            ->expects($this->once())
            ->method('existsActiveByEventoAndHouseholdAndNormalizedName')
            ->with($evento, $creador, $normalizedName)
            ->willReturn(false);
        $invitadoRepository
            ->expects($this->once())
            ->method('findDeletedByEventoAndHouseholdAndNormalizedName')
            ->with($evento, $creador, $normalizedName)
            ->willReturn($invitadoBorrado);

        $processor = new InvitadoPostProcessor($entityManager, $invitadoRepository);

        $result = $processor->process($nuevoInvitado, $this->createMock(Operation::class));

        $this->assertSame($invitadoBorrado, $result);
        $this->assertNull($invitadoBorrado->getDeletedAt());
        $this->assertSame('Ána', $invitadoBorrado->getNombre());
        $this->assertSame('Invitada', $invitadoBorrado->getApellidos());
        $this->assertSame(TipoPersonaEnum::INFANTIL, $invitadoBorrado->getTipoPersona());
        $this->assertSame('Observación nueva', $invitadoBorrado->getObservaciones());
    }

    private function createUser(string $nombre, string $apellidos): Usuario
    {
        $user = new Usuario();
        $user->setNombre($nombre);
        $user->setApellidos($apellidos);

        return $user;
    }

}
