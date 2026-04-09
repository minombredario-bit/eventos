<?php

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Operation;
use App\Dto\SeleccionParticipantesInput;
use App\Entity\Entidad;
use App\Entity\Evento;
use App\Entity\Invitado;
use App\Entity\Usuario;
use App\Repository\EventoRepository;
use App\Repository\InscripcionRepository;
use App\Repository\InvitadoRepository;
use App\Repository\SeleccionParticipanteEventoLineaRepository;
use App\Repository\SeleccionParticipanteEventoRepository;
use App\Repository\UsuarioRepository;
use App\Service\EmailQueueService;
use App\State\SeleccionParticipantesEventoPutProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SeleccionParticipantesEventoPutProcessorTest extends TestCase
{
    public function testProcessSyncsGranularSelectionAndReturnsSnapshot(): void
    {
        $entidad = $this->createMock(Entidad::class);
        $entidad->method('getId')->willReturn('entidad-1');

        $user = $this->createMock(Usuario::class);
        $user->method('getId')->willReturn('user-main');
        $user->method('getEntidad')->willReturn($entidad);

        $evento = $this->createMock(Evento::class);
        $evento->method('getId')->willReturn('evento-1');
        $evento->method('getEntidad')->willReturn($entidad);
        $evento->method('tieneActividadesActivas')->willReturn(true);
        $evento->method('permiteGestionInvitadosConActividades')->willReturn(true);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $eventoRepository = $this->createMock(EventoRepository::class);
        $eventoRepository->method('find')->with('evento-1')->willReturn($evento);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->exactly(2))->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $seleccionParticipanteEventoRepository = $this->createMock(SeleccionParticipanteEventoRepository::class);
        $seleccionParticipanteEventoRepository
            ->method('findByEventoAndInscritoPorUsuario')
            ->with($evento, $user)
            ->willReturn([]);

        $seleccionParticipanteEventoLineaRepository = $this->createMock(SeleccionParticipanteEventoLineaRepository::class);

        $usuarioParticipante = $this->createMock(Usuario::class);
        $usuarioParticipante->method('getId')->willReturn('user-1');
        $usuarioParticipante->method('getEntidad')->willReturn($entidad);

        $usuarioRepository = $this->createMock(UsuarioRepository::class);
        $usuarioRepository->method('find')->with('user-1')->willReturn($usuarioParticipante);

        $invitado = $this->createMock(Invitado::class);
        $invitado->method('getId')->willReturn('externo-1');

        $invitadoRepository = $this->createMock(InvitadoRepository::class);
        $invitadoRepository->method('resolveHouseholdUserIds')->with($user)->willReturn(['user-1']);
        $invitadoRepository
            ->method('findActiveByIdAndEventoAndHouseholdUsuario')
            ->with('externo-1', $evento, $user)
            ->willReturn($invitado);

        $inscripcionRepository = $this->createMock(InscripcionRepository::class);
        $inscripcionRepository->method('findOneByUsuarioAndEvento')->willReturn(null);
        $inscripcionRepository->method('findOneByInvitadoAndEvento')->willReturn(null);

        $processor = new SeleccionParticipantesEventoPutProcessor(
            $security,
            $eventoRepository,
            $entityManager,
            $seleccionParticipanteEventoRepository,
            $seleccionParticipanteEventoLineaRepository,
            $usuarioRepository,
            $invitadoRepository,
            $inscripcionRepository,
            $this->createMock(EmailQueueService::class),
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
    }

    public function testProcessRejectsWhenTypedIdAndOrigenAreInconsistent(): void
    {
        $entidad = $this->createMock(Entidad::class);
        $entidad->method('getId')->willReturn('entidad-1');

        $user = $this->createMock(Usuario::class);
        $user->method('getId')->willReturn('user-main');
        $user->method('getEntidad')->willReturn($entidad);

        $evento = $this->createMock(Evento::class);
        $evento->method('getId')->willReturn('evento-1');
        $evento->method('getEntidad')->willReturn($entidad);
        $evento->method('tieneActividadesActivas')->willReturn(true);
        $evento->method('permiteGestionInvitadosConActividades')->willReturn(true);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $eventoRepository = $this->createMock(EventoRepository::class);
        $eventoRepository->method('find')->with('evento-1')->willReturn($evento);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $processor = new SeleccionParticipantesEventoPutProcessor(
            $security,
            $eventoRepository,
            $entityManager,
            $this->createMock(SeleccionParticipanteEventoRepository::class),
            $this->createMock(SeleccionParticipanteEventoLineaRepository::class),
            $this->createMock(UsuarioRepository::class),
            $this->createMock(InvitadoRepository::class),
            $this->createMock(InscripcionRepository::class),
            $this->createMock(EmailQueueService::class),
        );

        $input = new SeleccionParticipantesInput();
        $input->participantes = [
            ['id' => '/api/invitados/nf-1', 'origen' => 'familiar'],
        ];

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Origen inconsistente con ID en índice 0');

        $processor->process($input, $this->createMock(Operation::class), ['eventoId' => 'evento-1']);
    }
}
