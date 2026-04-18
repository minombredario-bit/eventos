<?php

declare(strict_types=1);

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
            $tipoEntidad = $manager->getRepository(TipoEntidad::class)->findOneBy(['codigo' => $case->value]);

            if (!$tipoEntidad instanceof TipoEntidad) {
                $tipoEntidad = new TipoEntidad();
            }

            $tipoEntidad->setCodigo($case->value);
            $tipoEntidad->setNombre($case->label());
            $tipoEntidad->setDescripcion('Tipo autogenerado desde fixtures a partir del enum');
            $tipoEntidad->setActivo(true);
            $manager->persist($tipoEntidad);

            $this->addReference('tipo_entidad.' . strtolower($case->value), $tipoEntidad);
        }

        $manager->flush();
    }
}

