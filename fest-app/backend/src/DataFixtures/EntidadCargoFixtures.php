<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Cargo;
use App\Entity\Entidad;
use App\Entity\EntidadCargo;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class EntidadCargoFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var Entidad $entidad */
        $entidad = $this->getReference('entidad.demo', Entidad::class);

        $cargosPersonalizados = [
            ['cargo.vocal_apoyo', 'Vocal de apoyo', 20],
            ['cargo.vocal_infantil_apoyo', 'Vocal infantil de apoyo', 21],
        ];

        foreach ($cargosPersonalizados as [$cargoRef, $nombre, $orden]) {
            /** @var Cargo $cargo */
            $cargo = $this->getReference($cargoRef, Cargo::class);

            $entidadCargo = $manager->getRepository(EntidadCargo::class)->findOneBy([
                'entidad' => $entidad,
                'cargo' => $cargo,
            ]);

            if (!$entidadCargo instanceof EntidadCargo) {
                $entidadCargo = new EntidadCargo();
            }

            $entidadCargo->setEntidad($entidad);
            $entidadCargo->setCargo($cargo);
            $entidadCargo->setCargoMaster(null);
            $entidadCargo->setNombre($nombre);
            $entidadCargo->setOrden($orden);
            $entidadCargo->setActivo(true);

            $manager->persist($entidadCargo);
            $this->addReference(sprintf('entidad_cargo.%s', str_replace('cargo.', '', $cargoRef)), $entidadCargo);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class, CargoFixtures::class];
    }
}

