<?php

namespace App\Tests\Unit\Controller;

use App\Controller\EventoController;
use App\Entity\Invitado;
use App\Entity\SeleccionParticipantesEvento;
use App\Entity\Usuario;
use App\Repository\EventoRepository;
use App\Repository\InscripcionRepository;
use App\Repository\InvitadoRepository;
use App\Repository\SeleccionParticipantesEventoRepository;
use App\Repository\UsuarioRepository;
use App\Service\InscripcionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

class EventoControllerTest extends TestCase
{
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

        $seleccion = (new SeleccionParticipantesEvento())
            ->setUsuario($usuario)
            ->setEvento($evento)
            ->setParticipantes([
                ['id' => 'nf-77', 'origen' => 'invitado'],
            ]);

        $eventoRepository = $this->createMock(EventoRepository::class);
        $eventoRepository->method('find')->with('evento-1')->willReturn($evento);

        $invitadoRepository = $this->createMock(InvitadoRepository::class);
        $invitadoRepository
            ->method('resolveHouseholdUserIds')
            ->with($usuario)
            ->willReturn(['user-1']);

        $invitado = (new Invitado())
            ->setNombre('Ana')
            ->setApellidos('Invitada');

        $invitadoRepository
            ->expects($this->once())
            ->method('findActiveByIdAndEventoAndHouseholdUsuario')
            ->with('nf-77', $evento, $usuario)
            ->willReturn($invitado);

        $seleccionRepository = $this->createMock(SeleccionParticipantesEventoRepository::class);
        $seleccionRepository
            ->method('findByUsuarioIdsAndEvento')
            ->with(['user-1'], $evento)
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

        $seleccion = (new SeleccionParticipantesEvento())
            ->setUsuario($usuario)
            ->setEvento($evento)
            ->setParticipantes([
                ['id' => 'nf-borrado', 'origen' => 'invitado'],
            ]);

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
            ->with('nf-borrado', $evento, $usuario)
            ->willReturn(null);

        $seleccionRepository = $this->createMock(SeleccionParticipantesEventoRepository::class);
        $seleccionRepository
            ->method('findByUsuarioIdsAndEvento')
            ->with(['user-1'], $evento)
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
        SeleccionParticipantesEventoRepository $seleccionRepository,
    ): EventoController {
        $controller = $this->getMockBuilder(EventoController::class)
            ->setConstructorArgs([
                $eventoRepository,
                $this->createMock(InscripcionService::class),
                $inscripcionRepository,
                $invitadoRepository,
                $usuarioRepository,
                $seleccionRepository,
                $this->createMock(EntityManagerInterface::class),
            ])
            ->onlyMethods(['getUser'])
            ->getMock();

        $controller->method('getUser')->willReturn($usuario);
        $controller->setContainer(new Container());

        return $controller;
    }
}
