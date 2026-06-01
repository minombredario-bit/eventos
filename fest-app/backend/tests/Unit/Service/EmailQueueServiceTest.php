<?php

namespace App\Tests\Unit\Service;

use App\Entity\Entidad;
use App\Entity\Evento;
use App\Entity\Inscripcion;
use App\Entity\InscripcionLinea;
use App\Entity\Usuario;
use App\Enum\EstadoLineaInscripcionEnum;
use App\Enum\EstadoInscripcionEnum;
use App\Enum\EstadoPagoEnum;
use App\Repository\ColaCorreoRepository;
use App\Service\EmailQueueService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;

class EmailQueueServiceTest extends TestCase
{
    public function testEnqueueSkipsWhenRecipientIsNullOrBlank(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $service = new EmailQueueService(
            $entityManager,
            $this->createMock(ColaCorreoRepository::class),
            $this->createMock(Environment::class),
            $this->createMock(MailerInterface::class),
            'https://festivapp.es',
        );

        $this->assertNull($service->enqueue(null, 'Asunto', 'email/base.html.twig'));
        $this->assertNull($service->enqueue('   ', 'Asunto', 'email/base.html.twig'));
    }

    public function testEnqueueInscripcionCambioSkipsWhenUsuarioHasNoEmail(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $usuario = $this->createMock(Usuario::class);
        $usuario->method('getEmail')->willReturn(null);

        $inscripcion = $this->createMock(Inscripcion::class);
        $inscripcion->method('getUsuario')->willReturn($usuario);
        $inscripcion->expects($this->never())->method('getEvento');

        $service = new EmailQueueService(
            $entityManager,
            $this->createMock(ColaCorreoRepository::class),
            $this->createMock(Environment::class),
            $this->createMock(MailerInterface::class),
            'https://festivapp.es',
        );

        $service->enqueueInscripcionCambio($inscripcion, 'actualizado');

        $this->addToAssertionCount(1);
    }

    public function testEnqueueInscripcionCambioQueuesEmailWhenUsuarioHasEmail(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(static function (object $item): bool {
                return method_exists($item, 'getDestinatario')
                    && $item->getDestinatario() === 'user@example.com'
                    && method_exists($item, 'getAsunto')
                    && $item->getAsunto() === 'Actualización de inscripción: Cena popular';
            }));

        $usuario = $this->createMock(Usuario::class);
        $usuario->method('getEmail')->willReturn('USER@EXAMPLE.COM');
        $usuario->method('getNombre')->willReturn('Liam');
        $usuario->method('getEntidad')->willReturn($this->createMock(Entidad::class));

        $evento = $this->createMock(Evento::class);
        $evento->method('getTitulo')->willReturn('Cena popular');

        $lineaActiva = $this->createMock(InscripcionLinea::class);
        $lineaActiva->method('getEstadoLinea')->willReturn(EstadoLineaInscripcionEnum::PENDIENTE);
        $lineaActiva->method('getNombrePersonaSnapshot')->willReturn('Liam Andrés Esperanza');
        $lineaActiva->method('getNombreActividadSnapshot')->willReturn('Cena de gala');
        $lineaActiva->method('getFranjaComidaSnapshot')->willReturn('cena');
        $lineaActiva->method('getPrecioUnitario')->willReturn(15.5);

        $lineaCancelada = $this->createMock(InscripcionLinea::class);
        $lineaCancelada->method('getEstadoLinea')->willReturn(EstadoLineaInscripcionEnum::CANCELADA);

        $inscripcion = $this->createMock(Inscripcion::class);
        $inscripcion->method('getUsuario')->willReturn($usuario);
        $inscripcion->method('getEvento')->willReturn($evento);
        $inscripcion->method('getEstadoInscripcion')->willReturn(EstadoInscripcionEnum::CONFIRMADA);
        $inscripcion->method('getEstadoPago')->willReturn(EstadoPagoEnum::PENDIENTE);
        $inscripcion->method('getCodigo')->willReturn('ABC-2026-123456');
        $inscripcion->method('getImporteTotal')->willReturn(15.5);
        $inscripcion->method('getImportePagado')->willReturn(0.0);
        $inscripcion->method('getMoneda')->willReturn('EUR');
        $inscripcion->method('getLineas')->willReturn(new ArrayCollection([$lineaActiva, $lineaCancelada]));

        $service = new EmailQueueService(
            $entityManager,
            $this->createMock(ColaCorreoRepository::class),
            $this->createMock(Environment::class),
            $this->createMock(MailerInterface::class),
            'https://festivapp.es',
        );

        $service->enqueueInscripcionCambio($inscripcion, 'actualizado');

        $this->addToAssertionCount(1);
    }

    public function testEnqueueInscripcionCambioIncludesSummaryWhenUserSignsUp(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(static function (object $item): bool {
                if (!method_exists($item, 'getAsunto') || !method_exists($item, 'getContexto')) {
                    return false;
                }

                $contexto = $item->getContexto();
                $resumen = $contexto['resumenLineas'] ?? null;

                return $item->getAsunto() === 'Resumen de tu inscripción: Cena popular'
                    && is_array($resumen)
                    && count($resumen) === 1
                    && ($contexto['accionLabel'] ?? null) === 'Te has apuntado a nuevas actividades'
                    && ($resumen[0]['persona'] ?? null) === 'Yolanda Esperanza Pólit'
                    && ($resumen[0]['actividad'] ?? null) === 'Cena popular'
                    && ($resumen[0]['franja'] ?? null) === 'cena';
            }));

        $usuario = $this->createMock(Usuario::class);
        $usuario->method('getEmail')->willReturn('resumen@example.com');
        $usuario->method('getNombre')->willReturn('Yolanda');
        $usuario->method('getEntidad')->willReturn($this->createMock(Entidad::class));

        $evento = $this->createMock(Evento::class);
        $evento->method('getTitulo')->willReturn('Cena popular');

        $linea = $this->createMock(InscripcionLinea::class);
        $linea->method('getEstadoLinea')->willReturn(EstadoLineaInscripcionEnum::PENDIENTE);
        $linea->method('getNombrePersonaSnapshot')->willReturn('Yolanda Esperanza Pólit');
        $linea->method('getNombreActividadSnapshot')->willReturn('Cena popular');
        $linea->method('getFranjaComidaSnapshot')->willReturn('cena');
        $linea->method('getPrecioUnitario')->willReturn(12.0);

        $inscripcion = $this->createMock(Inscripcion::class);
        $inscripcion->method('getUsuario')->willReturn($usuario);
        $inscripcion->method('getEvento')->willReturn($evento);
        $inscripcion->method('getEstadoInscripcion')->willReturn(EstadoInscripcionEnum::PENDIENTE);
        $inscripcion->method('getEstadoPago')->willReturn(EstadoPagoEnum::PENDIENTE);
        $inscripcion->method('getCodigo')->willReturn('XYZ-2026-654321');
        $inscripcion->method('getImporteTotal')->willReturn(12.0);
        $inscripcion->method('getImportePagado')->willReturn(0.0);
        $inscripcion->method('getMoneda')->willReturn('EUR');
        $inscripcion->method('getLineas')->willReturn(new ArrayCollection([$linea]));

        $service = new EmailQueueService(
            $entityManager,
            $this->createMock(ColaCorreoRepository::class),
            $this->createMock(Environment::class),
            $this->createMock(MailerInterface::class),
            'https://festivapp.es',
        );

        $service->enqueueInscripcionCambio($inscripcion, 'apuntado');

        $this->addToAssertionCount(1);
    }
}

