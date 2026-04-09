<?php

namespace App\Tests\Unit\Controller;

use App\Controller\EventoController;
use App\Entity\Inscripcion;
use App\Entity\InscripcionLinea;
use App\Entity\Invitado;
use App\Entity\MenuEvento;
use App\Entity\SeleccionParticipanteEvento;
use App\Entity\Usuario;
use App\Enum\EstadoInscripcionEnum;
use App\Enum\EstadoLineaInscripcionEnum;
use App\Enum\EstadoPagoEnum;
use App\Repository\EventoRepository;
use App\Repository\InscripcionRepository;
use App\Repository\InvitadoRepository;
use App\Repository\SeleccionParticipanteEventoRepository;
use App\Repository\UsuarioRepository;
use App\Service\InscripcionService;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

class EventoControllerTest extends TestCase
{
    public function testInscribirmeAcceptsActividadPayloadAndReturnsDualAliases(): void
    {
        $entidad = $this->createConfiguredMock(\App\Entity\Entidad::class, ['getId' => 'entidad-1']);

        $evento = $this->createConfiguredMock(\App\Entity\Evento::class, [
            'getId' => 'evento-1',
            'getEntidad' => $entidad,
        ]);

        $usuario = $this->createConfiguredMock(Usuario::class, [
            'getId' => 'user-1',
            'getEntidad' => $entidad,
        ]);

        $linea = $this->createConfiguredMock(InscripcionLinea::class, [
            'getId' => 'linea-1',
            'getNombrePersonaSnapshot' => 'Ana Invitada',
            'getTipoPersonaSnapshot' => 'adulto',
            'getFranjaComidaSnapshot' => 'comida',
            'getNombreActividadSnapshot' => 'Taller de paella',
            'getActividad' => $this->createConfiguredMock(MenuEvento::class, ['getId' => 'act-1']),
            'getPrecioUnitario' => 12.5,
            'isEsDePagoSnapshot' => true,
            'getEstadoLinea' => EstadoLineaInscripcionEnum::PENDIENTE,
            'isPagada' => false,
        ]);

        $inscripcion = $this->createConfiguredMock(Inscripcion::class, [
            'getId' => 'insc-1',
            'getCodigo' => 'ENT-2026-AAAAAA',
            'getEstadoInscripcion' => EstadoInscripcionEnum::PENDIENTE,
            'getEstadoPago' => EstadoPagoEnum::PENDIENTE,
            'getImporteTotal' => 12.5,
            'getImportePagado' => 0.0,
            'getLineas' => new ArrayCollection([$linea]),
        ]);

        $inscripcionService = $this->createMock(InscripcionService::class);
        $inscripcionService
            ->expects($this->once())
            ->method('crearInscripcion')
            ->with(
                $evento,
                $usuario,
                $this->callback(static fn(array $personas): bool => ($personas[0]['actividad'] ?? null) === '/api/menu_eventos/act-1'),
            )
            ->willReturn($inscripcion);

        $eventoRepository = $this->createMock(EventoRepository::class);
        $eventoRepository->method('find')->with('evento-1')->willReturn($evento);

        $controller = $this->buildController(
            $usuario,
            $eventoRepository,
            $this->createMock(InscripcionRepository::class),
            $this->createMock(InvitadoRepository::class),
            $this->createMock(UsuarioRepository::class),
            $this->createMock(SeleccionParticipanteEventoRepository::class),
            $inscripcionService,
        );

        $request = Request::create('/api/eventos/evento-1/inscribirme', 'POST', [], [], [], [], json_encode([
            'personas' => [[
                'usuario' => '/api/usuarios/user-1',
                'actividad' => '/api/menu_eventos/act-1',
            ]],
        ],
            \JSON_THROW_ON_ERROR));

        $response = $controller->inscribirme('evento-1', $request);
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('act-1', $payload['lineas'][0]['actividadId']);
        $this->assertSame('act-1', $payload['lineas'][0]['menuId']);
        $this->assertSame('Taller de paella', $payload['lineas'][0]['nombreActividadSnapshot']);
        $this->assertSame('Taller de paella', $payload['lineas'][0]['nombreMenuSnapshot']);
    }

    public function testInscribirmeAcceptsLegacyMenuPayload(): void
    {
        $entidad = $this->createConfiguredMock(\App\Entity\Entidad::class, ['getId' => 'entidad-1']);

        $evento = $this->createConfiguredMock(\App\Entity\Evento::class, [
            'getId' => 'evento-1',
            'getEntidad' => $entidad,
        ]);

        $usuario = $this->createConfiguredMock(Usuario::class, [
            'getId' => 'user-1',
            'getEntidad' => $entidad,
        ]);

        $inscripcion = $this->createConfiguredMock(Inscripcion::class, [
            'getId' => 'insc-1',
            'getCodigo' => 'ENT-2026-BBBBBB',
            'getEstadoInscripcion' => EstadoInscripcionEnum::PENDIENTE,
            'getEstadoPago' => EstadoPagoEnum::PENDIENTE,
            'getImporteTotal' => 0.0,
            'getImportePagado' => 0.0,
            'getLineas' => new ArrayCollection([]),
        ]);

        $inscripcionService = $this->createMock(InscripcionService::class);
        $inscripcionService
            ->expects($this->once())
            ->method('crearInscripcion')
            ->with(
                $evento,
                $usuario,
                $this->callback(static fn(array $personas): bool => ($personas[0]['menu'] ?? null) === '/api/menu_eventos/legacy-1'),
            )
            ->willReturn($inscripcion);

        $eventoRepository = $this->createMock(EventoRepository::class);
        $eventoRepository->method('find')->with('evento-1')->willReturn($evento);

        $controller = $this->buildController(
            $usuario,
            $eventoRepository,
            $this->createMock(InscripcionRepository::class),
            $this->createMock(InvitadoRepository::class),
            $this->createMock(UsuarioRepository::class),
            $this->createMock(SeleccionParticipanteEventoRepository::class),
            $inscripcionService,
        );

        $request = Request::create('/api/eventos/evento-1/inscribirme', 'POST', [], [], [], [], json_encode([
            'personas' => [[
                'usuario' => '/api/usuarios/user-1',
                'menu' => '/api/menu_eventos/legacy-1',
            ]],
        ], \JSON_THROW_ON_ERROR));

        $response = $controller->inscribirme('evento-1', $request);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function testMenusLegacyRouteReturnsDeprecationHeaders(): void
    {
        $evento = $this->createConfiguredMock(\App\Entity\Evento::class, [
            'getMenus' => new ArrayCollection([]),
        ]);

        $eventoRepository = $this->createMock(EventoRepository::class);
        $eventoRepository->method('find')->with('evento-1')->willReturn($evento);

        $controller = $this->buildController(
            $this->createMock(Usuario::class),
            $eventoRepository,
            $this->createMock(InscripcionRepository::class),
            $this->createMock(InvitadoRepository::class),
            $this->createMock(UsuarioRepository::class),
            $this->createMock(SeleccionParticipanteEventoRepository::class),
        );

        $request = Request::create('/api/menu_eventos', 'GET', ['evento' => 'evento-1']);
        $request->attributes->set('_route', 'api_menu_eventos_by_evento');

        $response = $controller->menus($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('true', $response->headers->get('Deprecation'));
        $this->assertSame('Wed, 31 Dec 2026 23:59:59 GMT', $response->headers->get('Sunset'));
        $this->assertSame('</api/actividad_eventos>; rel="successor-version"', $response->headers->get('Link'));
    }

    public function testMenusCanonicalRouteDoesNotReturnDeprecationHeaders(): void
    {
        $evento = $this->createConfiguredMock(\App\Entity\Evento::class, [
            'getMenus' => new ArrayCollection([]),
        ]);

        $eventoRepository = $this->createMock(EventoRepository::class);
        $eventoRepository->method('find')->with('evento-1')->willReturn($evento);

        $controller = $this->buildController(
            $this->createMock(Usuario::class),
            $eventoRepository,
            $this->createMock(InscripcionRepository::class),
            $this->createMock(InvitadoRepository::class),
            $this->createMock(UsuarioRepository::class),
            $this->createMock(SeleccionParticipanteEventoRepository::class),
        );

        $request = Request::create('/api/actividad_eventos', 'GET', ['evento' => 'evento-1']);
        $request->attributes->set('_route', 'api_actividad_eventos_by_evento');

        $response = $controller->menus($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($response->headers->get('Deprecation'));
        $this->assertNull($response->headers->get('Sunset'));
        $this->assertNull($response->headers->get('Link'));
    }

    public function testGetApuntadosIncludesSelectedInvitadoWhenStillActive(): void
    {
        $entidad = $this->createConfiguredMock(\App\Entity\Entidad::class, [
            'getId' => 'entidad-1',
        ]);

        $evento = $this->createConfiguredMock(\App\Entity\Evento::class, [
            'getId' => 'evento-1',
            'getTitulo' => 'Comida de abril',
            'getFechaEvento' => new \DateTimeImmutable('2026-04-01'),
            'getEntidad' => $entidad,
        ]);

        $usuario = $this->createConfiguredMock(Usuario::class, [
            'getId' => 'user-1',
            'getEntidad' => $entidad,
        ]);

        $invitadoSeleccionado = (new Invitado())
            ->setNombre('Ana')
            ->setApellidos('Invitada');

        $seleccion = (new SeleccionParticipanteEvento())
            ->setEvento($evento)
            ->setInscritoPorUsuario($usuario)
            ->setUsuario(null)
            ->setInvitado($invitadoSeleccionado);

        $eventoRepository = $this->createMock(EventoRepository::class);
        $eventoRepository->method('find')->with('evento-1')->willReturn($evento);

        $invitadoRepository = $this->createMock(InvitadoRepository::class);
        $invitadoRepository
            ->method('resolveHouseholdUserIds')
            ->with($usuario)
            ->willReturn(['user-1']);

        $invitadoRepository
            ->expects($this->once())
            ->method('findActiveByIdAndEventoAndHouseholdUsuario')
            ->with((string) $invitadoSeleccionado->getId(), $evento, $usuario)
            ->willReturn($invitadoSeleccionado);

        $seleccionRepository = $this->createMock(SeleccionParticipanteEventoRepository::class);
        $seleccionRepository
            ->method('findByEventoAndInscritoPorUsuarioIds')
            ->with($evento, ['user-1'])
            ->willReturn([$seleccion]);

        $inscripcion = $this->createConfiguredMock(\App\Entity\Inscripcion::class, [
            'getId' => 'insc-1',
            'getLineas' => new ArrayCollection([]),
        ]);

        $inscripcionRepository = $this->createMock(InscripcionRepository::class);
        $inscripcionRepository
            ->method('findOneByInvitadoAndEvento')
            ->with((string) $invitadoSeleccionado->getId(), 'evento-1')
            ->willReturn($inscripcion);

        $controller = $this->buildController(
            $usuario,
            $eventoRepository,
            $inscripcionRepository,
            $invitadoRepository,
            $this->createMock(UsuarioRepository::class),
            $seleccionRepository,
        );

        $response = $controller->getApuntados('evento-1', new Request());
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, $payload['hydra:totalItems']);
        $this->assertCount(1, $payload['hydra:member']);
        $this->assertSame('Ana Invitada', $payload['hydra:member'][0]['nombreCompleto']);
    }

    public function testGetApuntadosOmitsDeletedInvitadoFromVisibleList(): void
    {
        $entidad = $this->createConfiguredMock(\App\Entity\Entidad::class, [
            'getId' => 'entidad-1',
        ]);

        $evento = $this->createConfiguredMock(\App\Entity\Evento::class, [
            'getId' => 'evento-1',
            'getTitulo' => 'Comida de abril',
            'getFechaEvento' => new \DateTimeImmutable('2026-04-01'),
            'getEntidad' => $entidad,
        ]);

        $usuario = $this->createConfiguredMock(Usuario::class, [
            'getId' => 'user-1',
            'getEntidad' => $entidad,
        ]);

        $invitadoSeleccionado = (new Invitado())
            ->setNombre('Ana')
            ->setApellidos('Invitada');

        $seleccion = (new SeleccionParticipanteEvento())
            ->setEvento($evento)
            ->setInscritoPorUsuario($usuario)
            ->setUsuario(null)
            ->setInvitado($invitadoSeleccionado);

        $eventoRepository = $this->createMock(EventoRepository::class);
        $eventoRepository->method('find')->with('evento-1')->willReturn($evento);

        $invitadoRepository = $this->createMock(InvitadoRepository::class);
        $invitadoRepository
            ->method('resolveHouseholdUserIds')
            ->with($usuario)
            ->willReturn(['user-1']);
        $invitadoRepository
            ->expects($this->once())
            ->method('findActiveByIdAndEventoAndHouseholdUsuario')
            ->with((string) $invitadoSeleccionado->getId(), $evento, $usuario)
            ->willReturn(null);

        $seleccionRepository = $this->createMock(SeleccionParticipanteEventoRepository::class);
        $seleccionRepository
            ->method('findByEventoAndInscritoPorUsuarioIds')
            ->with($evento, ['user-1'])
            ->willReturn([$seleccion]);

        $controller = $this->buildController(
            $usuario,
            $eventoRepository,
            $this->createMock(InscripcionRepository::class),
            $invitadoRepository,
            $this->createMock(UsuarioRepository::class),
            $seleccionRepository,
        );

        $response = $controller->getApuntados('evento-1', new Request());
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, $payload['hydra:totalItems']);
        $this->assertSame([], $payload['hydra:member']);
    }

    private function buildController(
        Usuario $usuario,
        EventoRepository $eventoRepository,
        InscripcionRepository $inscripcionRepository,
        InvitadoRepository $invitadoRepository,
        UsuarioRepository $usuarioRepository,
        SeleccionParticipanteEventoRepository $seleccionRepository,
        ?InscripcionService $inscripcionService = null,
    ): EventoController {
        $controller = $this->getMockBuilder(EventoController::class)
            ->setConstructorArgs([
                $eventoRepository,
                $inscripcionService ?? $this->createMock(InscripcionService::class),
                $inscripcionRepository,
                $invitadoRepository,
                $usuarioRepository,
                $seleccionRepository,
            ])
            ->onlyMethods(['getUser'])
            ->getMock();

        $controller->method('getUser')->willReturn($usuario);
        $controller->setContainer(new Container());

        return $controller;
    }
}
