<?php

namespace App\Tests\Unit\Service;

use App\Entity\Evento;
use App\Entity\Inscripcion;
use App\Entity\Usuario;
use App\Repository\EventoRepository;
use App\Repository\InscripcionRepository;
use App\Repository\InvitadoRepository;
use App\Repository\MenuEventoRepository;
use App\Repository\RelacionUsuarioRepository;
use App\Repository\UsuarioRepository;
use App\Service\EmailQueueService;
use App\Service\InscripcionService;
use App\Service\PriceCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class InscripcionServicePayloadAliasTest extends TestCase
{
    public function testCrearInscripcionUsesActividadWhenActividadAndMenuArePresent(): void
    {
        $menuRepo = $this->createMock(MenuEventoRepository::class);
        $menuRepo->expects($this->once())
            ->method('find')
            ->with('actividad-prioritaria')
            ->willReturn(null);

        $service = $this->buildService($menuRepo);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Actividad no encontrada');

        $service->crearInscripcion('evento-1', 'user-1', [[
            'usuario' => '/api/usuarios/user-1',
            'actividad' => '/api/menu_eventos/actividad-prioritaria',
            'menu' => '/api/menu_eventos/menu-legacy',
        ]]);
    }

    public function testCrearInscripcionAcceptsLegacyMenuPayloadWhenActividadIsMissing(): void
    {
        $menuRepo = $this->createMock(MenuEventoRepository::class);
        $menuRepo->expects($this->once())
            ->method('find')
            ->with('menu-legacy')
            ->willReturn(null);

        $service = $this->buildService($menuRepo);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Actividad no encontrada');

        $service->crearInscripcion('evento-1', 'user-1', [[
            'usuario' => '/api/usuarios/user-1',
            'menu' => '/api/menu_eventos/menu-legacy',
        ]]);
    }

    public function testCrearInscripcionUsesActividadIdOverMenuId(): void
    {
        $menuRepo = $this->createMock(MenuEventoRepository::class);
        $menuRepo->expects($this->once())
            ->method('find')
            ->with('actividad-id-prioritaria')
            ->willReturn(null);

        $service = $this->buildService($menuRepo);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Actividad no encontrada');

        $service->crearInscripcion('evento-1', 'user-1', [[
            'usuario' => '/api/usuarios/user-1',
            'actividad_id' => 'actividad-id-prioritaria',
            'menu_id' => 'menu-id-legacy',
        ]]);
    }

    public function testCrearInscripcionAcceptsLegacyMenuIdPayloadWhenActividadIdIsMissing(): void
    {
        $menuRepo = $this->createMock(MenuEventoRepository::class);
        $menuRepo->expects($this->once())
            ->method('find')
            ->with('menu-id-legacy')
            ->willReturn(null);

        $service = $this->buildService($menuRepo);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Actividad no encontrada');

        $service->crearInscripcion('evento-1', 'user-1', [[
            'usuario' => '/api/usuarios/user-1',
            'menu_id' => 'menu-id-legacy',
        ]]);
    }

    private function buildService(MenuEventoRepository $menuRepo): InscripcionService
    {
        $evento = $this->createMock(Evento::class);
        $evento->method('isPublicado')->willReturn(true);
        $evento->method('estaInscripcionAbierta')->willReturn(true);
        $evento->method('getId')->willReturn('evento-1');

        $usuario = $this->createMock(Usuario::class);
        $usuario->method('getId')->willReturn('user-1');

        $eventoRepository = $this->createMock(EventoRepository::class);
        $eventoRepository->method('find')->with('evento-1')->willReturn($evento);

        $usuarioRepository = $this->createMock(UsuarioRepository::class);
        $usuarioRepository->method('find')->with('user-1')->willReturn($usuario);

        $inscripcionRepository = $this->createMock(InscripcionRepository::class);
        $inscripcionRepository
            ->method('findOneByUsuarioAndEvento')
            ->with('user-1', 'evento-1')
            ->willReturn(new Inscripcion());

        return new InscripcionService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(PriceCalculatorService::class),
            $this->createMock(EmailQueueService::class),
            $inscripcionRepository,
            $eventoRepository,
            $this->createMock(RelacionUsuarioRepository::class),
            $menuRepo,
            $this->createMock(InvitadoRepository::class),
            $usuarioRepository,
        );
    }
}

