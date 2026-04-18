<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\CargoMaster;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class CargoMasterFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $masters = [
            ['presidente', 'Presidente', 'PRESIDENTE', 'Máximo representante de la comisión', true],
            ['vicepresidente', 'Vicepresidente', 'VICEPRESIDENTE', 'Miembro de junta directiva', true],
            ['secretario', 'Secretario', 'SECRETARIO', 'Responsable administrativo', true],
            ['tesorero', 'Tesorero', 'TESORERO', 'Responsable económico', true],
            ['fallera_mayor', 'Fallera Mayor', 'FALLERA_MAYOR', 'Representante principal femenina', true],
            ['presidente_infantil', 'Presidente Infantil', 'PRESIDENTE_INFANTIL', 'Representación infantil', true],
            ['fallera_mayor_infantil', 'Fallera Mayor Infantil', 'FALLERA_MAYOR_INFANTIL', 'Representante infantil femenina', true],
            ['capitan', 'Capitán', 'CAPITAN', 'Cargo festero representativo', true],
            ['alferez', 'Alférez', 'ALFEREZ', 'Cargo festero representativo', true],
            ['abanderado', 'Abanderado/a', 'ABANDERADO', 'Portador de la bandera', true],
        ];

        foreach ($masters as [$ref, $nombre, $codigo, $descripcion, $activo]) {
            $cargoMaster = new CargoMaster();
            $cargoMaster->setNombre($nombre);
            $cargoMaster->setCodigo($codigo);
            $cargoMaster->setDescripcion($descripcion);
            $cargoMaster->setActivo($activo);
            $manager->persist($cargoMaster);
            $this->addReference('cargo_master.' . $ref, $cargoMaster);
        }

        $manager->flush();
    }
}

