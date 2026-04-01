<?php

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Operation;
use App\Entity\Entidad;
use App\Entity\Evento;
use App\Entity\SeleccionParticipantesEvento;
use App\Entity\Usuario;
use App\Repository\EventoRepository;
use App\Repository\InscripcionRepository;
use App\Repository\InvitadoRepository;
use App\Repository\SeleccionParticipantesEventoRepository;
use App\Repository\UsuarioRepository;
use App\State\SeleccionParticipantesEventoProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class SeleccionParticipantesEventoProviderTest extends TestCase
{
    public function testProvideExcludesLogicallyDeletedInvitadosFromVisibleSelection(): void
    {
        $entidad = $this->createMock(Entidad::class);
        $entidad->method('getId')->willReturn('entidad-1');

        $user = $this->createMock(Usuario::class);
        $user->method('getEntidad')->willReturn($entidad);

        $evento = $this->createMock(Evento::class);
        $evento->method('getId')->willReturn('evento-1');
        $evento->method('getEntidad')->willReturn($entidad);

        $seleccion = (new SeleccionParticipantesEvento())
            ->setUsuario($user)
            ->setEvento($evento)
            ->setParticipantes([
                ['id' => 'nf-borrado', 'origen' => 'invitado'],
                ['id' => 'u-2', 'origen' => 'familiar'],
            ]);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $eventoRepository = $this->createMock(EventoRepository::class);
        $eventoRepository->method('find')->with('evento-1')->willReturn($evento);

        $seleccionRepository = $this->createMock(SeleccionParticipantesEventoRepository::class);
        $seleccionRepository
            ->method('findOneByUsuarioAndEvento')
            ->with($user, $evento)
            ->willReturn($seleccion);

        $usuarioRelacionado = $this->createMock(Usuario::class);
        $usuarioRelacionado->method('getId')->willReturn('u-2');
        $usuarioRelacionado->method('getNombre')->willReturn('María');
        $usuarioRelacionado->method('getApellidos')->willReturn('García');

        $usuarioRepository = $this->createMock(UsuarioRepository::class);
        $usuarioRepository->method('find')->with('u-2')->willReturn($usuarioRelacionado);

        $inscripcionRepository = $this->createMock(InscripcionRepository::class);
        $inscripcionRepository->method('findOneByUsuarioAndEvento')->willReturn(null);

        $invitadoRepository = $this->createMock(InvitadoRepository::class);
        $invitadoRepository
            ->expects($this->once())
            ->method('findActiveByIdAndEventoAndHouseholdUsuario')
            ->with('nf-borrado', $evento, $user)
            ->willReturn(null);

        $provider = new SeleccionParticipantesEventoProvider(
            $security,
            $eventoRepository,
            $seleccionRepository,
            $usuarioRepository,
            $inscripcionRepository,
            $invitadoRepository,
        );

        $response = $provider->provide($this->createMock(Operation::class), ['eventoId' => 'evento-1']);

        $this->assertSame('evento-1', $response->eventoId);
        $this->assertCount(1, $response->participantes);
        $this->assertSame('u-2', $response->participantes[0]['id']);
        $this->assertSame('familiar', $response->participantes[0]['origen']);
    }

    public function testProvideKeepsInvitadoSelectedWhenCreatedByRelatedUser(): void
    {
        $entidad = $this->createMock(Entidad::class);
        $entidad->method('getId')->willReturn('entidad-1');

        $user = $this->createMock(Usuario::class);
        $user->method('getEntidad')->willReturn($entidad);

        $evento = $this->createMock(Evento::class);
        $evento->method('getId')->willReturn('evento-1');
        $evento->method('getEntidad')->willReturn($entidad);

        $seleccion = (new SeleccionParticipantesEvento())
            ->setUsuario($user)
            ->setEvento($evento)
            ->setParticipantes([
                ['id' => 'nf-compartido', 'origen' => 'invitado'],
            ]);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $eventoRepository = $this->createMock(EventoRepository::class);
        $eventoRepository->method('find')->with('evento-1')->willReturn($evento);

        $seleccionRepository = $this->createMock(SeleccionParticipantesEventoRepository::class);
        $seleccionRepository
            ->method('findOneByUsuarioAndEvento')
            ->with($user, $evento)
            ->willReturn($seleccion);

        $usuarioRepository = $this->createMock(UsuarioRepository::class);
        $usuarioRepository->expects($this->never())->method('find');

        $inscripcionRepository = $this->createMock(InscripcionRepository::class);
        $inscripcionRepository->expects($this->never())->method('findOneByUsuarioAndEvento');

        $invitado = new \App\Entity\Invitado();
        $invitado->setNombre('Ana')->setApellidos('Compartida');

        $invitadoRepository = $this->createMock(InvitadoRepository::class);
        $invitadoRepository
            ->expects($this->once())
            ->method('findActiveByIdAndEventoAndHouseholdUsuario')
            ->with('nf-compartido', $evento, $user)
            ->willReturn($invitado);

        $provider = new SeleccionParticipantesEventoProvider(
            $security,
            $eventoRepository,
            $seleccionRepository,
            $usuarioRepository,
            $inscripcionRepository,
            $invitadoRepository,
        );

        $response = $provider->provide($this->createMock(Operation::class), ['eventoId' => 'evento-1']);

        $this->assertCount(1, $response->participantes);
        $this->assertSame('nf-compartido', $response->participantes[0]['id']);
        $this->assertSame('invitado', $response->participantes[0]['origen']);
        $this->assertSame('Ana', $response->participantes[0]['nombre']);
    }
}
