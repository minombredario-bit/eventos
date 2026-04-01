<?php

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Operation;
use App\Dto\SeleccionParticipantesInput;
use App\Entity\Entidad;
use App\Entity\Evento;
use App\Entity\SeleccionParticipantesEvento;
use App\Entity\Usuario;
use App\Repository\EventoRepository;
use App\Repository\SeleccionParticipantesEventoRepository;
use App\State\SeleccionParticipantesEventoPutProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class SeleccionParticipantesEventoPutProcessorTest extends TestCase
{
    public function testProcessUpdatesLatestSelectionAndRemovesHistoricalDuplicates(): void
    {
        $entidad = $this->createMock(Entidad::class);
        $entidad->method('getId')->willReturn('entidad-1');

        $user = $this->createMock(Usuario::class);
        $user->method('getEntidad')->willReturn($entidad);

        $evento = $this->createMock(Evento::class);
        $evento->method('getId')->willReturn('evento-1');
        $evento->method('getEntidad')->willReturn($entidad);

        $latest = (new SeleccionParticipantesEvento())
            ->setUsuario($user)
            ->setEvento($evento)
            ->setParticipantes([['id' => 'old', 'origen' => 'familiar']]);

        $historicalDuplicate = (new SeleccionParticipantesEvento())
            ->setUsuario($user)
            ->setEvento($evento)
            ->setParticipantes([['id' => 'stale', 'origen' => 'familiar']]);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $eventoRepository = $this->createMock(EventoRepository::class);
        $eventoRepository->method('find')->with('evento-1')->willReturn($evento);

        $seleccionRepository = $this->createMock(SeleccionParticipantesEventoRepository::class);
        $seleccionRepository
            ->expects($this->once())
            ->method('findByUsuarioAndEventoOrdered')
            ->with($user, $evento)
            ->willReturn([$latest, $historicalDuplicate]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->once())->method('remove')->with($historicalDuplicate);
        $entityManager->expects($this->once())->method('flush');

        $processor = new SeleccionParticipantesEventoPutProcessor(
            $security,
            $eventoRepository,
            $seleccionRepository,
            $entityManager,
        );

        $input = new SeleccionParticipantesInput();
        $input->participantes = [
            ['id' => '/api/usuarios/user-1', 'origen' => 'familiar'],
            ['id' => 'externo-1', 'origen' => 'invitado'],
        ];

        $response = $processor->process($input, $this->createMock(Operation::class), ['eventoId' => 'evento-1']);

        $this->assertSame('evento-1', $response->eventoId);
        $this->assertSame([
            ['id' => 'user-1', 'origen' => 'familiar'],
            ['id' => 'externo-1', 'origen' => 'invitado'],
        ], $response->participantes);
        $this->assertNotNull($response->updatedAt);
        $this->assertSame($response->participantes, $latest->getParticipantes());
    }
}
