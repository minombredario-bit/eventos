<?php

namespace App\Tests\Unit\Service;

use App\Entity\MenuEvento;
use App\Entity\PersonaFamiliar;
use App\Enum\EstadoValidacionEnum;
use App\Enum\TipoMenuEnum;
use App\Enum\TipoPersonaEnum;
use App\Enum\TipoRelacionEconomicaEnum;
use App\Service\PriceCalculatorService;
use PHPUnit\Framework\TestCase;

class PriceCalculatorServiceTest extends TestCase
{
    private PriceCalculatorService $calculator;

    protected function setUp(): void
    {
        $this->calculator = new PriceCalculatorService();
    }

    /**
     * Helper to create a mock MenuEvento
     */
    private function createMenu(
        TipoMenuEnum $tipo = TipoMenuEnum::ADULTO,
        bool $esDePago = true,
        ?float $precioBase = 15.00,
        ?float $precioAdultoInterno = 12.00,
        ?float $precioAdultoExterno = 25.00,
        ?float $precioInfantil = 10.00
    ): MenuEvento {
        $menu = $this->createMock(MenuEvento::class);
        $menu->method('isEsDePago')->willReturn($esDePago);
        $menu->method('getTipoMenu')->willReturn($tipo);
        $menu->method('getPrecioBase')->willReturn($precioBase ?? 15.00);
        $menu->method('getPrecioAdultoInterno')->willReturn($precioAdultoInterno);
        $menu->method('getPrecioAdultoExterno')->willReturn($precioAdultoExterno);
        $menu->method('getPrecioInfantil')->willReturn($precioInfantil);
        $menu->method('getNombre')->willReturn('Menú Test');
        return $menu;
    }

    /**
     * Helper to create a mock PersonaFamiliar
     */
    private function createPersona(
        TipoPersonaEnum $tipo = TipoPersonaEnum::ADULTO,
        TipoRelacionEconomicaEnum $relacion = TipoRelacionEconomicaEnum::INTERNO,
        EstadoValidacionEnum $validacion = EstadoValidacionEnum::VALIDADO
    ): PersonaFamiliar {
        $persona = $this->createMock(PersonaFamiliar::class);
        $persona->method('getTipoPersona')->willReturn($tipo);
        $persona->method('getTipoRelacionEconomica')->willReturn($relacion);
        $persona->method('getEstadoValidacion')->willReturn($validacion);
        $persona->method('getNombreCompleto')->willReturn('Juan García');
        return $persona;
    }

    // ==========================================
    // RULE 1: Free Event Tests
    // ==========================================

    public function testFreeEventReturnsZero(): void
    {
        $menu = $this->createMenu(esDePago: false);
        $persona = $this->createPersona();

        $price = $this->calculator->calcularPrecio($menu, $persona);

        $this->assertEquals(0.00, $price);
    }

    // ==========================================
    // RULE 2: Infant Menu Tests
    // ==========================================

    public function testInfantMenuForAdultoPersonaReturnsInfantilPrice(): void
    {
        $menu = $this->createMenu(tipo: TipoMenuEnum::INFANTIL, precioInfantil: 10.00);
        $persona = $this->createPersona(tipo: TipoPersonaEnum::ADULTO);

        $price = $this->calculator->calcularPrecio($menu, $persona);

        $this->assertEquals(10.00, $price);
    }

    public function testInfantMenuForInfantilPersonaReturnsInfantilPrice(): void
    {
        $menu = $this->createMenu(tipo: TipoMenuEnum::INFANTIL, precioInfantil: 10.00);
        $persona = $this->createPersona(tipo: TipoPersonaEnum::INFANTIL);

        $price = $this->calculator->calcularPrecio($menu, $persona);

        $this->assertEquals(10.00, $price);
    }

    public function testInfantMenuForExternoPersonaReturnsInfantilPrice(): void
    {
        $menu = $this->createMenu(tipo: TipoMenuEnum::INFANTIL, precioInfantil: 10.00);
        $persona = $this->createPersona(relacion: TipoRelacionEconomicaEnum::EXTERNO);

        $price = $this->calculator->calcularPrecio($menu, $persona);

        $this->assertEquals(10.00, $price);
    }

    public function testInfantMenuFallsBackToBasePriceWhenInfantilIsNull(): void
    {
        $menu = $this->createMenu(tipo: TipoMenuEnum::INFANTIL, precioInfantil: null, precioBase: 8.00);
        $persona = $this->createPersona();

        $price = $this->calculator->calcularPrecio($menu, $persona);

        $this->assertEquals(8.00, $price);
    }

    // ==========================================
    // RULE 3: Adult Menu - Internal Validated
    // ==========================================

    public function testAdultMenuForInternoValidatedReturnsInternoPrice(): void
    {
        $menu = $this->createMenu(
            tipo: TipoMenuEnum::ADULTO,
            precioAdultoInterno: 12.00,
            precioAdultoExterno: 25.00
        );
        $persona = $this->createPersona(
            relacion: TipoRelacionEconomicaEnum::INTERNO,
            validacion: EstadoValidacionEnum::VALIDADO
        );

        $price = $this->calculator->calcularPrecio($menu, $persona);

        $this->assertEquals(12.00, $price);
    }

    public function testAdultMenuForInternoPendingValidationReturnsExternoPrice(): void
    {
        $menu = $this->createMenu(
            tipo: TipoMenuEnum::ADULTO,
            precioAdultoInterno: 12.00,
            precioAdultoExterno: 25.00
        );
        $persona = $this->createPersona(
            relacion: TipoRelacionEconomicaEnum::INTERNO,
            validacion: EstadoValidacionEnum::PENDIENTE_VALIDACION
        );

        $price = $this->calculator->calcularPrecio($menu, $persona);

        $this->assertEquals(25.00, $price);
    }

    // ==========================================
    // RULE 4: Adult Menu - External or Invitado
    // ==========================================

    public function testAdultMenuForExternoReturnsExternoPrice(): void
    {
        $menu = $this->createMenu(
            tipo: TipoMenuEnum::ADULTO,
            precioAdultoInterno: 12.00,
            precioAdultoExterno: 25.00
        );
        $persona = $this->createPersona(relacion: TipoRelacionEconomicaEnum::EXTERNO);

        $price = $this->calculator->calcularPrecio($menu, $persona);

        $this->assertEquals(25.00, $price);
    }

    public function testAdultMenuForInvitadoReturnsExternoPrice(): void
    {
        $menu = $this->createMenu(
            tipo: TipoMenuEnum::ADULTO,
            precioAdultoInterno: 12.00,
            precioAdultoExterno: 25.00
        );
        $persona = $this->createPersona(relacion: TipoRelacionEconomicaEnum::INVITADO);

        $price = $this->calculator->calcularPrecio($menu, $persona);

        $this->assertEquals(25.00, $price);
    }

    // ==========================================
    // FALLBACK TESTS
    // ==========================================

    public function testFallsBackToBasePriceWhenAdultoInternoIsNull(): void
    {
        $menu = $this->createMenu(
            tipo: TipoMenuEnum::ADULTO,
            precioAdultoInterno: null,
            precioAdultoExterno: null,
            precioBase: 15.00
        );
        $persona = $this->createPersona(
            relacion: TipoRelacionEconomicaEnum::INTERNO,
            validacion: EstadoValidacionEnum::VALIDADO
        );

        $price = $this->calculator->calcularPrecio($menu, $persona);

        $this->assertEquals(15.00, $price);
    }

    public function testFallsBackToBasePriceWhenAdultoExternoIsNull(): void
    {
        $menu = $this->createMenu(
            tipo: TipoMenuEnum::ADULTO,
            precioAdultoInterno: 12.00,
            precioAdultoExterno: null,
            precioBase: 15.00
        );
        $persona = $this->createPersona(relacion: TipoRelacionEconomicaEnum::EXTERNO);

        $price = $this->calculator->calcularPrecio($menu, $persona);

        $this->assertEquals(15.00, $price);
    }

    // ==========================================
    // SPECIAL MENU TYPE TESTS
    // ==========================================

    public function testSpecialMenuForInternoValidatedReturnsInternoPrice(): void
    {
        $menu = $this->createMenu(
            tipo: TipoMenuEnum::ESPECIAL,
            precioAdultoInterno: 18.00,
            precioAdultoExterno: 30.00
        );
        $persona = $this->createPersona(
            relacion: TipoRelacionEconomicaEnum::INTERNO,
            validacion: EstadoValidacionEnum::VALIDADO
        );

        $price = $this->calculator->calcularPrecio($menu, $persona);

        $this->assertEquals(18.00, $price);
    }

    public function testLibreMenuForInternoValidatedReturnsInternoPrice(): void
    {
        $menu = $this->createMenu(
            tipo: TipoMenuEnum::LIBRE,
            precioAdultoInterno: 10.00,
            precioAdultoExterno: 20.00
        );
        $persona = $this->createPersona(
            relacion: TipoRelacionEconomicaEnum::INTERNO,
            validacion: EstadoValidacionEnum::VALIDADO
        );

        $price = $this->calculator->calcularPrecio($menu, $persona);

        $this->assertEquals(10.00, $price);
    }

    // ==========================================
    // CALCULAR TOTAL TESTS
    // ==========================================

    public function testCalcularTotalSumsMultipleLines(): void
    {
        $menu1 = $this->createMenu(tipo: TipoMenuEnum::ADULTO, precioAdultoInterno: 12.00);
        $persona1 = $this->createPersona(relacion: TipoRelacionEconomicaEnum::INTERNO, validacion: EstadoValidacionEnum::VALIDADO);
        
        $menu2 = $this->createMenu(tipo: TipoMenuEnum::INFANTIL, precioInfantil: 10.00);
        $persona2 = $this->createPersona(tipo: TipoPersonaEnum::INFANTIL);

        $lineas = [
            ['menu' => $menu1, 'persona' => $persona1],
            ['menu' => $menu2, 'persona' => $persona2],
        ];

        $total = $this->calculator->calcularTotal($lineas);

        $this->assertEquals(22.00, $total);
    }

    public function testCalcularTotalWithFreeEvent(): void
    {
        $menu1 = $this->createMenu(tipo: TipoMenuEnum::ADULTO, esDePago: false);
        $menu2 = $this->createMenu(tipo: TipoMenuEnum::INFANTIL, esDePago: false);
        $persona = $this->createPersona();

        $lineas = [
            ['menu' => $menu1, 'persona' => $persona],
            ['menu' => $menu2, 'persona' => $persona],
        ];

        $total = $this->calculator->calcularTotal($lineas);

        $this->assertEquals(0.00, $total);
    }
}
