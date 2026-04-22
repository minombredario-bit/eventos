<?php

namespace App\Tests\Unit\Service;

use App\Entity\ActividadEvento;
use App\Enum\EstadoValidacionEnum;
use App\Enum\TipoActividadEnum;
use App\Enum\TipoPersonaEnum;
use App\Enum\TipoRelacionEconomicaEnum;
use App\Service\PriceCalculatorService;
use App\Entity\Usuario;
use PHPUnit\Framework\TestCase;

// Minimal placeholder used by the tests for mocking purposes. The real application
// represents personas with several domain classes; tests mock this type.
// Tests expect a lightweight user-like object; use the real Usuario class for
// compatibility with PriceCalculatorService which accepts Usuario instances.

class PriceCalculatorServiceTest extends TestCase
{
    private PriceCalculatorService $calculator;

    protected function setUp(): void
    {
        $this->calculator = new PriceCalculatorService();
    }

    /**
     * Helper to create a mock ActividadEvento
     */
    private function createActividad(
        TipoActividadEnum $tipo = TipoActividadEnum::ADULTO,
        bool $esDePago = true,
        ?float $precioBase = 15.00,
        ?float $precioAdultoInterno = 12.00,
        ?float $precioAdultoExterno = 25.00,
        ?float $precioInfantil = 10.00
    ): ActividadEvento {
        $actividad = $this->createMock(ActividadEvento::class);
        $actividad->method('isEsDePago')->willReturn($esDePago);
        $actividad->method('getTipoActividad')->willReturn($tipo);
        $actividad->method('getPrecioBase')->willReturn($precioBase ?? 15.00);
        $actividad->method('getPrecioAdultoInterno')->willReturn($precioAdultoInterno);
        $actividad->method('getPrecioAdultoExterno')->willReturn($precioAdultoExterno);
        $actividad->method('getPrecioInfantil')->willReturn($precioInfantil);
        $actividad->method('getNombre')->willReturn('Menú Test');
        return $actividad;
    }

    /**
     * Helper to create a mock PersonaFamiliar
     */
    private function createPersona(
        TipoPersonaEnum $tipo = TipoPersonaEnum::ADULTO,
        TipoRelacionEconomicaEnum $relacion = TipoRelacionEconomicaEnum::INTERNO,
        EstadoValidacionEnum $validacion = EstadoValidacionEnum::VALIDADO
    ): Usuario {
        $usuario = $this->createMock(Usuario::class);
        // PriceCalculatorService expects methods getEstadoValidacion() and
        // getTipoUsuarioEconomico() on Usuario
        $usuario->method('getEstadoValidacion')->willReturn($validacion);
        $usuario->method('getTipoUsuarioEconomico')->willReturn($relacion);
        $usuario->method('getNombreCompleto')->willReturn('Juan García');
        return $usuario;
    }

    // ==========================================
    // RULE 1: Free Event Tests
    // ==========================================

    public function testFreeEventReturnsZero(): void
    {
        $actividad = $this->createActividad(esDePago: false);
        $persona = $this->createPersona();

        $price = $this->calculator->calcularPrecio($actividad, $persona);

        $this->assertEquals(0.00, $price);
    }

    // ==========================================
    // RULE 2: Infant Actividad Tests
    // ==========================================

    public function testInfantActividadForAdultoPersonaReturnsInfantilPrice(): void
    {
        $actividad = $this->createActividad(tipo: TipoActividadEnum::INFANTIL, precioInfantil: 10.00);
        $persona = $this->createPersona(tipo: TipoPersonaEnum::ADULTO);

        $price = $this->calculator->calcularPrecio($actividad, $persona);

        $this->assertEquals(10.00, $price);
    }

    public function testInfantActividadForInfantilPersonaReturnsInfantilPrice(): void
    {
        $actividad = $this->createActividad(tipo: TipoActividadEnum::INFANTIL, precioInfantil: 10.00);
        $persona = $this->createPersona(tipo: TipoPersonaEnum::INFANTIL);

        $price = $this->calculator->calcularPrecio($actividad, $persona);

        $this->assertEquals(10.00, $price);
    }

    public function testInfantActividadForExternoPersonaReturnsInfantilPrice(): void
    {
        $actividad = $this->createActividad(tipo: TipoActividadEnum::INFANTIL, precioInfantil: 10.00);
        $persona = $this->createPersona(relacion: TipoRelacionEconomicaEnum::EXTERNO);

        $price = $this->calculator->calcularPrecio($actividad, $persona);

        $this->assertEquals(10.00, $price);
    }

    public function testInfantActividadFallsBackToBasePriceWhenInfantilIsNull(): void
    {
        $actividad = $this->createActividad(tipo: TipoActividadEnum::INFANTIL, precioInfantil: null, precioBase: 8.00);
        $persona = $this->createPersona();

        $price = $this->calculator->calcularPrecio($actividad, $persona);

        $this->assertEquals(8.00, $price);
    }

    // ==========================================
    // RULE 3: Adult Actividad - Internal Validated
    // ==========================================

    public function testAdultActividadForInternoValidatedReturnsInternoPrice(): void
    {
        $actividad = $this->createActividad(
            tipo: TipoActividadEnum::ADULTO,
            precioAdultoInterno: 12.00,
            precioAdultoExterno: 25.00
        );
        $persona = $this->createPersona(
            relacion: TipoRelacionEconomicaEnum::INTERNO,
            validacion: EstadoValidacionEnum::VALIDADO
        );

        $price = $this->calculator->calcularPrecio($actividad, $persona);

        $this->assertEquals(12.00, $price);
    }

    public function testAdultActividadForInternoPendingValidationReturnsExternoPrice(): void
    {
        $actividad = $this->createActividad(
            tipo: TipoActividadEnum::ADULTO,
            precioAdultoInterno: 12.00,
            precioAdultoExterno: 25.00
        );
        $persona = $this->createPersona(
            relacion: TipoRelacionEconomicaEnum::INTERNO,
            validacion: EstadoValidacionEnum::PENDIENTE_VALIDACION
        );

        $price = $this->calculator->calcularPrecio($actividad, $persona);

        $this->assertEquals(25.00, $price);
    }

    // ==========================================
    // RULE 4: Adult Actividad - External or Invitado
    // ==========================================

    public function testAdultActividadForExternoReturnsExternoPrice(): void
    {
        $actividad = $this->createActividad(
            tipo: TipoActividadEnum::ADULTO,
            precioAdultoInterno: 12.00,
            precioAdultoExterno: 25.00
        );
        $persona = $this->createPersona(relacion: TipoRelacionEconomicaEnum::EXTERNO);

        $price = $this->calculator->calcularPrecio($actividad, $persona);

        $this->assertEquals(25.00, $price);
    }

    public function testAdultActividadForInvitadoReturnsExternoPrice(): void
    {
        $actividad = $this->createActividad(
            tipo: TipoActividadEnum::ADULTO,
            precioAdultoInterno: 12.00,
            precioAdultoExterno: 25.00
        );
        $persona = $this->createPersona(relacion: TipoRelacionEconomicaEnum::INVITADO);

        $price = $this->calculator->calcularPrecio($actividad, $persona);

        $this->assertEquals(25.00, $price);
    }

    // ==========================================
    // FALLBACK TESTS
    // ==========================================

    public function testFallsBackToBasePriceWhenAdultoInternoIsNull(): void
    {
        $actividad = $this->createActividad(
            tipo: TipoActividadEnum::ADULTO,
            precioAdultoInterno: null,
            precioAdultoExterno: null,
            precioBase: 15.00
        );
        $persona = $this->createPersona(
            relacion: TipoRelacionEconomicaEnum::INTERNO,
            validacion: EstadoValidacionEnum::VALIDADO
        );

        $price = $this->calculator->calcularPrecio($actividad, $persona);

        $this->assertEquals(15.00, $price);
    }

    public function testFallsBackToBasePriceWhenAdultoExternoIsNull(): void
    {
        $actividad = $this->createActividad(
            tipo: TipoActividadEnum::ADULTO,
            precioAdultoInterno: 12.00,
            precioAdultoExterno: null,
            precioBase: 15.00
        );
        $persona = $this->createPersona(relacion: TipoRelacionEconomicaEnum::EXTERNO);

        $price = $this->calculator->calcularPrecio($actividad, $persona);

        $this->assertEquals(15.00, $price);
    }

    // ==========================================
    // SPECIAL ACTIVIDAD TYPE TESTS
    // ==========================================

    public function testSpecialActividadForInternoValidatedReturnsInternoPrice(): void
    {
        $actividad = $this->createActividad(
            tipo: TipoActividadEnum::ESPECIAL,
            precioAdultoInterno: 18.00,
            precioAdultoExterno: 30.00
        );
        $persona = $this->createPersona(
            relacion: TipoRelacionEconomicaEnum::INTERNO,
            validacion: EstadoValidacionEnum::VALIDADO
        );

        $price = $this->calculator->calcularPrecio($actividad, $persona);

        $this->assertEquals(18.00, $price);
    }

    public function testLibreActividadForInternoValidatedReturnsInternoPrice(): void
    {
        $actividad = $this->createActividad(
            tipo: TipoActividadEnum::LIBRE,
            precioAdultoInterno: 10.00,
            precioAdultoExterno: 20.00
        );
        $persona = $this->createPersona(
            relacion: TipoRelacionEconomicaEnum::INTERNO,
            validacion: EstadoValidacionEnum::VALIDADO
        );

        $price = $this->calculator->calcularPrecio($actividad, $persona);

        $this->assertEquals(10.00, $price);
    }

    // ==========================================
    // CALCULAR TOTAL TESTS
    // ==========================================

    public function testCalcularTotalSumsMultipleLines(): void
    {
        $actividad1 = $this->createActividad(tipo: TipoActividadEnum::ADULTO, precioAdultoInterno: 12.00);
        $persona1 = $this->createPersona(relacion: TipoRelacionEconomicaEnum::INTERNO, validacion: EstadoValidacionEnum::VALIDADO);

        $actividad2 = $this->createActividad(tipo: TipoActividadEnum::INFANTIL, precioInfantil: 10.00);
        $persona2 = $this->createPersona(tipo: TipoPersonaEnum::INFANTIL);

        $lineas = [
            ['actividad' => $actividad1, 'persona' => $persona1],
            ['actividad' => $actividad2, 'persona' => $persona2],
        ];

        $total = $this->calculator->calcularTotal($lineas);

        $this->assertEquals(22.00, $total);
    }

    public function testCalcularTotalWithFreeEvent(): void
    {
        $actividad1 = $this->createActividad(tipo: TipoActividadEnum::ADULTO, esDePago: false);
        $actividad2 = $this->createActividad(tipo: TipoActividadEnum::INFANTIL, esDePago: false);
        $persona = $this->createPersona();

        $lineas = [
            ['actividad' => $actividad1, 'persona' => $persona],
            ['actividad' => $actividad2, 'persona' => $persona],
        ];

        $total = $this->calculator->calcularTotal($lineas);

        $this->assertEquals(0.00, $total);
    }
}
