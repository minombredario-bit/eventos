<?php

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Operation;
use App\Entity\Entidad;
use App\Entity\Evento;
use App\Entity\Invitado;
use App\Entity\Usuario;
use App\Enum\TipoPersonaEnum;
use App\Repository\EventoRepository;
use App\Repository\InvitadoRepository;
use App\State\EventoInvitadosProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class EventoInvitadosProviderTest extends TestCase
{
    public function testProvideListsHouseholdInvitadosForAnyMember(): void
    {
        $entidad = $this->createMock(Entidad::class);
        $entidad->method('getId')->willReturn('entidad-1');

        $user = $this->createMock(Usuario::class);
        $user->method('getEntidad')->willReturn($entidad);

        $evento = $this->createMock(Evento::class);
        $evento->method('getEntidad')->willReturn($entidad);

        $invitado = (new Invitado())
            ->setNombre('Ana')
            ->setApellidos('Núcleo')
            ->setTipoPersona(TipoPersonaEnum::ADULTO)
            ->setObservaciones('Compartido por relación');
        $invitado->syncNombreCompleto();

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $eventoRepository = $this->createMock(EventoRepository::class);
        $eventoRepository->method('find')->with('evt-1')->willReturn($evento);

        $invitadoRepository = $this->createMock(InvitadoRepository::class);
        $invitadoRepository
            ->expects($this->once())
            ->method('findByEventoAndHouseholdUsers')
            ->with($evento, $user)
            ->willReturn([$invitado]);

        $provider = new EventoInvitadosProvider($security, $eventoRepository, $invitadoRepository);

        $result = $provider->provide($this->createMock(Operation::class), ['id' => 'evt-1']);

        $this->assertCount(1, $result);
        $this->assertSame('Ana', $result[0]->nombre);
        $this->assertSame('Núcleo', $result[0]->apellidos);
        $this->assertSame('invitado', $result[0]->origen);
    }
}
