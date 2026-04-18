<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Cargo;
use App\Entity\Entidad;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class CargoFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var Entidad $entidad */
        $entidad = $this->getReference('entidad.demo', Entidad::class);

        $cargos = [
            ['presidente', 'Presidente', 'PRESIDENTE', 'Máximo representante de la comisión', true, true, false, false, 1, 1.00],
            ['vicepresidente', 'Vicepresidente', 'VICEPRESIDENTE', 'Miembro de junta directiva', true, false, false, false, 2, 0.50],
            ['secretario', 'Secretario', 'SECRETARIO', 'Responsable administrativo', true, false, false, false, 3, 0.50],
            ['tesorero', 'Tesorero', 'TESORERO', 'Responsable económico', true, false, false, false, 4, 0.50],
            ['fallera_mayor', 'Fallera Mayor', 'FALLERA_MAYOR', 'Representante principal femenina', false, true, false, false, 10, 1.00],
            ['presidente_infantil', 'Presidente Infantil', 'PRESIDENTE_INFANTIL', 'Representación infantil', false, true, true, true, 11, 0.50],
            ['fallera_mayor_infantil', 'Fallera Mayor Infantil', 'FALLERA_MAYOR_INFANTIL', 'Representante infantil femenina', false, true, true, true, 12, 0.50],
            ['capitan', 'Capitán', 'CAPITAN', 'Cargo festero representativo', false, true, false, false, 13, 1.00],
            ['alferez', 'Alférez', 'ALFEREZ', 'Cargo festero representativo', false, true, false, false, 14, 1.00],
            ['abanderado', 'Abanderado/a', 'ABANDERADO', 'Portador de la bandera', false, true, false, false, 15, 0.50],
            ['vocal_apoyo', 'Vocal de apoyo', 'VOCAL_APOYO', 'Cargo personalizado creado por la entidad', false, false, false, false, 20, 0.00],
            ['vocal_infantil_apoyo', 'Vocal infantil de apoyo', 'VOCAL_INFANTIL_APOYO', 'Cargo infantil personalizado creado por la entidad', false, false, true, true, 21, 0.00],
        ];

        foreach ($cargos as [$ref, $nombre, $codigo, $descripcion, $directivo, $representativo, $infantil, $infantilEspecial, $orden, $anios]) {
            $cargo = $manager->getRepository(Cargo::class)->findOneBy(['entidad' => $entidad, 'codigo' => $codigo]);

            if (!$cargo instanceof Cargo) {
                $cargo = new Cargo();
            }

            $cargo->setEntidad($entidad);
            $cargo->setNombre($nombre);
            $cargo->setCodigo($codigo);
            $cargo->setDescripcion($descripcion);
            $cargo->setComputaComoDirectivo($directivo);
            $cargo->setEsRepresentativo($representativo);
            $cargo->setEsInfantil($infantil);
            $cargo->setInfantilEspecial($infantilEspecial);
            $cargo->setActivo(true);
            $cargo->setOrdenJerarquico($orden);
            $manager->persist($cargo);
            $this->addReference('cargo.' . $ref, $cargo);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class, CargoMasterFixtures::class];
    }
}

