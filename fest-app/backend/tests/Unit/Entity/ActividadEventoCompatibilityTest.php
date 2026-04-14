<?php

namespace App\Tests\Unit\Entity;

use App\Entity\ActividadEvento;
use App\Enum\CompatibilidadPersonaActividadEnum;
use App\Enum\TipoPersonaEnum;
use PHPUnit\Framework\TestCase;

class ActividadEventoCompatibilityTest extends TestCase
{
    public function testAdultOnlyActividadRejectsInfantilPersona(): void
    {
        $actividad = new ActividadEvento();
        $actividad->setCompatibilidadPersona(CompatibilidadPersonaActividadEnum::ADULTO);

        $this->assertFalse($actividad->esCompatibleConTipoPersona(TipoPersonaEnum::INFANTIL));
    }

    public function testInfantilOnlyActividadRejectsAdultoPersona(): void
    {
        $actividad = new ActividadEvento();
        $actividad->setCompatibilidadPersona(CompatibilidadPersonaActividadEnum::INFANTIL);

        $this->assertFalse($actividad->esCompatibleConTipoPersona(TipoPersonaEnum::ADULTO));
    }

    public function testAmbosActividadAcceptsBothPersonaTypes(): void
    {
        $actividad = new ActividadEvento();
        $actividad->setCompatibilidadPersona(CompatibilidadPersonaActividadEnum::AMBOS);

        $this->assertTrue($actividad->esCompatibleConTipoPersona(TipoPersonaEnum::ADULTO));
        $this->assertTrue($actividad->esCompatibleConTipoPersona(TipoPersonaEnum::INFANTIL));
    }
}
