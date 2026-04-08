<?php

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Operation;
use App\Entity\Entidad;
use App\Entity\Evento;
use App\Entity\Inscripcion;
use App\Entity\InscripcionLinea;
use App\Entity\MenuEvento;
use App\Entity\SeleccionParticipanteEvento;
use App\Entity\Usuario;
use App\Enum\EstadoLineaInscripcionEnum;
use App\Enum\EstadoPagoEnum;
use App\Enum\TipoPersonaEnum;
use App\Repository\EventoRepository;
use App\Repository\InscripcionRepository;
use App\Repository\InvitadoRepository;
use App\Repository\SeleccionParticipanteEventoRepository;
use App\Repository\UsuarioRepository;
use App\State\SeleccionParticipantesEventoProvider;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class SeleccionParticipantesEventoProviderTest extends TestCase
{
    public function testProvideBuildsResponseFromGranularSelection(): void
    {
        $entidad = $this->createMock(Entidad::class);
        $entidad->method('getId')->willReturn('entidad-1');

        $user = $this->createMock(Usuario::class);
        $user->method('getEntidad')->willReturn($entidad);

        $evento = $this->createMock(Evento::class);
        $evento->method('getId')->willReturn('evento-1');
        $evento->method('getEntidad')->willReturn($entidad);
        $evento->method('tieneMenusActivos')->willReturn(true);
        $evento->method('permiteGestionInvitados')->willReturn(true);

        $participante = $this->createMock(Usuario::class);
        $participante->method('getId')->willReturn('u-2');
        $participante->method('getNombre')->willReturn('María');
        $participante->method('getApellidos')->willReturn('García');

        $seleccion = (new SeleccionParticipanteEvento())
            ->setEvento($evento)
            ->setInscritoPorUsuario($user)
            ->setUsuario($participante)
            ->setInvitado(null);

        $menu = $this->createMock(MenuEvento::class);
        $menu->method('getId')->willReturn('menu-1');

        $lineaActiva = $this->createMock(InscripcionLinea::class);
        $lineaActiva->method('getUsuario')->willReturn($participante);
        $lineaActiva->method('getEstadoLinea')->willReturn(EstadoLineaInscripcionEnum::PENDIENTE);
        $lineaActiva->method('getId')->willReturn('linea-1');
        $lineaActiva->method('getMenu')->willReturn($menu);
        $lineaActiva->method('getNombreMenuSnapshot')->willReturn('Menú especial');
        $lineaActiva->method('getFranjaComidaSnapshot')->willReturn('comida');
        $lineaActiva->method('getPrecioUnitario')->willReturn(12.5);
        $lineaActiva->method('getTipoPersonaSnapshot')->willReturn('adulto');

        $lineaCancelada = $this->createMock(InscripcionLinea::class);
        $lineaCancelada->method('getUsuario')->willReturn($participante);
        $lineaCancelada->method('getEstadoLinea')->willReturn(EstadoLineaInscripcionEnum::CANCELADA);

        $inscripcion = $this->createMock(Inscripcion::class);
        $inscripcion->method('getId')->willReturn('insc-1');
        $inscripcion->method('getCodigo')->willReturn('INS-001');
        $inscripcion->method('getEstadoPago')->willReturn(EstadoPagoEnum::PARCIAL);
        $inscripcion->method('getImportePagado')->willReturn(7.75);
        $inscripcion->method('getLineas')->willReturn(new ArrayCollection([$lineaActiva, $lineaCancelada]));

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $eventoRepository = $this->createMock(EventoRepository::class);
        $eventoRepository->method('find')->with('evento-1')->willReturn($evento);

        $usuarioRepository = $this->createMock(UsuarioRepository::class);
        $usuarioRepository->method('find')->with('u-2')->willReturn($participante);

        $inscripcionRepository = $this->createMock(InscripcionRepository::class);
        $inscripcionRepository->method('findOneByUsuarioAndEvento')->with('u-2', 'evento-1')->willReturn($inscripcion);

        $invitadoRepository = $this->createMock(InvitadoRepository::class);

        $seleccionRepository = $this->createMock(SeleccionParticipanteEventoRepository::class);
        $seleccionRepository->method('findByEventoAndInscritoPorUsuario')->with($evento, $user)->willReturn([$seleccion]);

        $provider = new SeleccionParticipantesEventoProvider(
            $security,
            $eventoRepository,
            $usuarioRepository,
            $inscripcionRepository,
            $invitadoRepository,
            $seleccionRepository,
        );

        $response = $provider->provide($this->createMock(Operation::class), ['eventoId' => 'evento-1']);

        $this->assertCount(1, $response->participantes);
        $this->assertArrayHasKey('inscripcionRelacion', $response->participantes[0]);
        $this->assertSame(12.5, $response->participantes[0]['inscripcionRelacion']['totalLineas']);
        $this->assertSame(7.75, $response->participantes[0]['inscripcionRelacion']['totalPagado']);
        $this->assertSame('u-2', $response->participantes[0]['inscripcionRelacion']['lineas'][0]['usuarioId']);
        $this->assertNull($response->participantes[0]['inscripcionRelacion']['lineas'][0]['invitadoId']);
    }

    public function testProvideExcludesDeletedInvitadoFromGranularSelection(): void
    {
        $entidad = $this->createMock(Entidad::class);
        $entidad->method('getId')->willReturn('entidad-1');

        $user = $this->createMock(Usuario::class);
        $user->method('getEntidad')->willReturn($entidad);

        $evento = $this->createMock(Evento::class);
        $evento->method('getId')->willReturn('evento-1');
        $evento->method('getEntidad')->willReturn($entidad);
        $evento->method('tieneMenusActivos')->willReturn(true);
        $evento->method('permiteGestionInvitados')->willReturn(true);

        $invitado = new \App\Entity\Invitado();
        $invitado->setNombre('Ana')->setApellidos('Compartida')->setTipoPersona(TipoPersonaEnum::ADULTO);

        $seleccion = (new SeleccionParticipanteEvento())
            ->setEvento($evento)
            ->setInscritoPorUsuario($user)
            ->setUsuario(null)
            ->setInvitado($invitado);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $eventoRepository = $this->createMock(EventoRepository::class);
        $eventoRepository->method('find')->with('evento-1')->willReturn($evento);

        $usuarioRepository = $this->createMock(UsuarioRepository::class);
        $usuarioRepository->expects($this->never())->method('find');

        $inscripcionRepository = $this->createMock(InscripcionRepository::class);
        $inscripcionRepository->method('findOneByInvitadoAndEvento')->willReturn(null);

        $invitadoRepository = $this->createMock(InvitadoRepository::class);
        $invitadoRepository
            ->expects($this->once())
            ->method('findActiveByIdAndEventoAndHouseholdUsuario')
            ->with((string) $invitado->getId(), $evento, $user)
            ->willReturn(null);

        $seleccionRepository = $this->createMock(SeleccionParticipanteEventoRepository::class);
        $seleccionRepository->method('findByEventoAndInscritoPorUsuario')->with($evento, $user)->willReturn([$seleccion]);

        $provider = new SeleccionParticipantesEventoProvider(
            $security,
            $eventoRepository,
            $usuarioRepository,
            $inscripcionRepository,
            $invitadoRepository,
            $seleccionRepository,
        );

        $response = $provider->provide($this->createMock(Operation::class), ['eventoId' => 'evento-1']);
        $this->assertSame([], $response->participantes);
    }

    public function testProvideReturnsEmptyResponseWhenEventoHasNoActiveMenus(): void
    {
        $entidad = $this->createMock(Entidad::class);
        $entidad->method('getId')->willReturn('entidad-1');

        $user = $this->createMock(Usuario::class);
        $user->method('getEntidad')->willReturn($entidad);

        $evento = $this->createMock(Evento::class);
        $evento->method('getId')->willReturn('evento-1');
        $evento->method('getEntidad')->willReturn($entidad);
        $evento->method('tieneMenusActivos')->willReturn(false);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $eventoRepository = $this->createMock(EventoRepository::class);
        $eventoRepository->method('find')->with('evento-1')->willReturn($evento);

        $usuarioRepository = $this->createMock(UsuarioRepository::class);
        $usuarioRepository->expects($this->never())->method('find');

        $inscripcionRepository = $this->createMock(InscripcionRepository::class);
        $inscripcionRepository->expects($this->never())->method('findOneByUsuarioAndEvento');
        $inscripcionRepository->expects($this->never())->method('findOneByInvitadoAndEvento');

        $invitadoRepository = $this->createMock(InvitadoRepository::class);

        $seleccionRepository = $this->createMock(SeleccionParticipanteEventoRepository::class);
        $seleccionRepository
            ->expects($this->never())
            ->method('findByEventoAndInscritoPorUsuario');

        $provider = new SeleccionParticipantesEventoProvider(
            $security,
            $eventoRepository,
            $usuarioRepository,
            $inscripcionRepository,
            $invitadoRepository,
            $seleccionRepository,
        );

        $response = $provider->provide($this->createMock(Operation::class), ['eventoId' => 'evento-1']);

        $this->assertSame('evento-1', $response->eventoId);
        $this->assertSame([], $response->participantes);
        $this->assertNull($response->updatedAt);
    }

    public function testProvideReturnsNullUpdatedAtWhenSelectionIsEmpty(): void
    {
        $entidad = $this->createMock(Entidad::class);
        $entidad->method('getId')->willReturn('entidad-1');

        $user = $this->createMock(Usuario::class);
        $user->method('getEntidad')->willReturn($entidad);

        $evento = $this->createMock(Evento::class);
        $evento->method('getId')->willReturn('evento-1');
        $evento->method('getEntidad')->willReturn($entidad);
        $evento->method('tieneMenusActivos')->willReturn(true);
        $evento->method('permiteGestionInvitados')->willReturn(true);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $eventoRepository = $this->createMock(EventoRepository::class);
        $eventoRepository->method('find')->with('evento-1')->willReturn($evento);

        $usuarioRepository = $this->createMock(UsuarioRepository::class);
        $usuarioRepository->expects($this->never())->method('find');

        $inscripcionRepository = $this->createMock(InscripcionRepository::class);
        $inscripcionRepository->expects($this->never())->method('findOneByUsuarioAndEvento');
        $inscripcionRepository->expects($this->never())->method('findOneByInvitadoAndEvento');

        $invitadoRepository = $this->createMock(InvitadoRepository::class);

        $seleccionRepository = $this->createMock(SeleccionParticipanteEventoRepository::class);
        $seleccionRepository->method('findByEventoAndInscritoPorUsuario')->with($evento, $user)->willReturn([]);

        $provider = new SeleccionParticipantesEventoProvider(
            $security,
            $eventoRepository,
            $usuarioRepository,
            $inscripcionRepository,
            $invitadoRepository,
            $seleccionRepository,
        );

        $response = $provider->provide($this->createMock(Operation::class), ['eventoId' => 'evento-1']);

        $this->assertSame('evento-1', $response->eventoId);
        $this->assertSame([], $response->participantes);
        $this->assertNull($response->updatedAt);
    }
}
