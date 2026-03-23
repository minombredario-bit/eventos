<?php

namespace App\Tests\Unit\Entity;

use App\Entity\MenuEvento;
use App\Enum\CompatibilidadPersonaMenuEnum;
use App\Enum\TipoPersonaEnum;
use PHPUnit\Framework\TestCase;

class MenuEventoCompatibilityTest extends TestCase
{
    public function testAdultOnlyMenuRejectsInfantilPersona(): void
    {
        $menu = new MenuEvento();
        $menu->setCompatibilidadPersona(CompatibilidadPersonaMenuEnum::ADULTO);

        $this->assertFalse($menu->esCompatibleConTipoPersona(TipoPersonaEnum::INFANTIL));
    }

    public function testInfantilOnlyMenuRejectsAdultoPersona(): void
    {
        $menu = new MenuEvento();
        $menu->setCompatibilidadPersona(CompatibilidadPersonaMenuEnum::INFANTIL);

        $this->assertFalse($menu->esCompatibleConTipoPersona(TipoPersonaEnum::ADULTO));
    }

    public function testAmbosMenuAcceptsBothPersonaTypes(): void
    {
        $menu = new MenuEvento();
        $menu->setCompatibilidadPersona(CompatibilidadPersonaMenuEnum::AMBOS);

        $this->assertTrue($menu->esCompatibleConTipoPersona(TipoPersonaEnum::ADULTO));
        $this->assertTrue($menu->esCompatibleConTipoPersona(TipoPersonaEnum::INFANTIL));
    }
}
