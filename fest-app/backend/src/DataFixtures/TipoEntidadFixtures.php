<?php

namespace App\DataFixtures;

use App\Entity\TipoEntidad;
use App\Enum\TipoEntidadEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TipoEntidadFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        foreach (TipoEntidadEnum::cases() as $case) {
            $te = new TipoEntidad();
            $te->setCodigo($case->value);
            $te->setNombre($case->label());
            $te->setDescripcion('Tipo autogenerado desde fixtures a partir del enum');
            $te->setActivo(true);
            $manager->persist($te);
        }

        $manager->flush();
    }
}

