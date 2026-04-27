<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Cargo;
use App\Entity\CargoMaster;
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

        // Crear también EntidadCargo para todos los CargoMaster (cargos oficiales)
        $cargoMasterRepo = $manager->getRepository(CargoMaster::class);
        $cargoMasters = $cargoMasterRepo->findAll();

        // If entidad is of type 'falla', only create EntidadCargo for the allowed CargoMaster codes.
        $allowedForFalla = [
            'DELEGADO_FESTEJOS', 'PRESIDENTE', 'PRESIDENTE_INFANTIL', 'VICESECRETARIO',
            'DELEGADO_PROTOCOLO', 'FALLERA_MAYOR_INFANTIL', 'VICEPRESIDENTE_1', 'TESORERO',
            'VICEPRESIDENTE_2', 'DELEGADO_CULTURA', 'FALLERA_MAYOR', 'DELEGADO_INFANTILES',
            'SECRETARIO', 'ABANDERADO_INFANTIL'
        ];

        foreach ($cargoMasters as $cargoMaster) {
            $codigoMaster = $cargoMaster->getCodigo() ?? strtolower($cargoMaster->getNombre());

            if ($entidad->getTipoEntidad()?->getCodigo() === 'falla' && !in_array($codigoMaster, $allowedForFalla, true)) {
                // skip cargoMasters not allowed for 'falla'
                continue;
            }
            $entidadCargo = $manager->getRepository(EntidadCargo::class)->findOneBy([
                'entidad' => $entidad,
                'cargoMaster' => $cargoMaster,
            ]);

            if (!$entidadCargo instanceof EntidadCargo) {
                $entidadCargo = new EntidadCargo();
                $entidadCargo->setEntidad($entidad);
                $entidadCargo->setCargoMaster($cargoMaster);
                $entidadCargo->setNombre(null);
                $entidadCargo->setOrden(null);
                $entidadCargo->setActivo(true);
                $manager->persist($entidadCargo);
            }

            // Registrar referencia usando el código del cargo master en minúsculas
            $this->setReference(sprintf('entidad_cargo.%s', strtolower($codigoMaster)), $entidadCargo);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class, CargoFixtures::class];
    }
}

