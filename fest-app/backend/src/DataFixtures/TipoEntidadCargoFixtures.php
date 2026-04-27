<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\CargoMaster;
use App\Entity\TipoEntidad;
use App\Entity\TipoEntidadCargo;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class TipoEntidadCargoFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $tipoEntidades = $manager->getRepository(TipoEntidad::class)->findAll();
        $cargoMasters = $manager->getRepository(CargoMaster::class)->findAll();

        foreach ($tipoEntidades as $tipoEntidad) {
            if (!$tipoEntidad instanceof TipoEntidad) {
                continue;
            }

            foreach ($cargoMasters as $cargoMaster) {
                if (!$cargoMaster instanceof CargoMaster) {
                    continue;
                }

                $tipoEntidadCargo = $manager->getRepository(TipoEntidadCargo::class)->findOneBy([
                    'tipoEntidad' => $tipoEntidad,
                    'cargoMaster' => $cargoMaster,
                ]);

                if (!$tipoEntidadCargo instanceof TipoEntidadCargo) {
                    $tipoEntidadCargo = new TipoEntidadCargo();
                }

                $tipoEntidadCargo->setTipoEntidad($tipoEntidad);
                $tipoEntidadCargo->setCargoMaster($cargoMaster);
                $tipoEntidadCargo->setActivo(true);
                $manager->persist($tipoEntidadCargo);

                $tipoCodigo = $tipoEntidad->getCodigo();
                $cargoCodigo = $cargoMaster->getCodigo() ?? strtolower($cargoMaster->getNombre());
                // Use setReference to allow running fixtures with --append without failing on duplicate keys
                $this->setReference(sprintf('tipo_entidad_cargo.%s.%s', strtolower($tipoCodigo), strtolower($cargoCodigo)), $tipoEntidadCargo);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [TipoEntidadFixtures::class, CargoMasterFixtures::class];
    }
}

