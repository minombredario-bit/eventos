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
            // =========================
            // FALLAS / JUNTA DIRECTIVA
            // =========================
            ['presidente', 'Presidente', 'PRESIDENTE', 'Máximo representante de la comisión', true, true, false, false, 1, 1.00],
            ['vicepresidente_1', 'Vicepresidente 1º', 'VICEPRESIDENTE_1', 'Miembro de junta directiva', true, false, false, false, 2, 0.75],
            ['vicepresidente_2', 'Vicepresidente 2º', 'VICEPRESIDENTE_2', 'Miembro de junta directiva', true, false, false, false, 3, 0.50],
            ['secretario', 'Secretario', 'SECRETARIO', 'Responsable administrativo', true, false, false, false, 4, 0.50],
            ['vicesecretario', 'Vicesecretario', 'VICESECRETARIO', 'Apoyo a la secretaría', true, false, false, false, 5, 0.25],
            ['tesorero', 'Tesorero', 'TESORERO', 'Responsable económico', true, false, false, false, 6, 0.50],
            ['contador', 'Contador', 'CONTADOR', 'Responsable de control económico y contable', true, false, false, false, 7, 0.25],
            ['delegado_festejos', 'Delegado de Festejos', 'DELEGADO_FESTEJOS', 'Responsable de festejos y actos', true, false, false, false, 8, 0.25],
            ['delegado_cultura', 'Delegado de Cultura', 'DELEGADO_CULTURA', 'Responsable de actividades culturales', true, false, false, false, 9, 0.25],
            ['delegado_protocolo', 'Delegado de Protocolo', 'DELEGADO_PROTOCOLO', 'Responsable de protocolo y organización institucional', true, false, false, false, 10, 0.25],
            ['delegado_infantiles', 'Delegado de Infantiles', 'DELEGADO_INFANTILES', 'Responsable del área infantil', true, false, true, false, 11, 0.25],

            // =========================
            // FALLAS / REPRESENTATIVOS
            // =========================
            ['fallera_mayor', 'Fallera Mayor', 'FALLERA_MAYOR', 'Representante principal femenina de la comisión', false, true, false, false, 20, 1.00],
            ['presidente_infantil', 'Presidente Infantil', 'PRESIDENTE_INFANTIL', 'Representante infantil masculino de la comisión', false, true, true, true, 21, 0.50],
            ['fallera_mayor_infantil', 'Fallera Mayor Infantil', 'FALLERA_MAYOR_INFANTIL', 'Representante infantil femenina de la comisión', false, true, true, true, 22, 0.50],

            // =========================
            // COMPARSAS / CARGOS FESTEROS
            // =========================
            ['primer_trono', 'Primer Trono', 'PRIMER_TRONO', 'Cargo festero representativo principal', false, true, false, false, 30, 1.00],
            ['segundo_trono', 'Segundo Trono', 'SEGUNDO_TRONO', 'Cargo festero representativo', false, true, false, false, 31, 0.75],
            ['capitan', 'Capitán', 'CAPITAN', 'Cargo festero representativo', false, true, false, false, 32, 1.00],
            ['alferez', 'Alférez', 'ALFEREZ', 'Cargo festero representativo', false, true, false, false, 33, 1.00],
            ['abanderado', 'Abanderado/a', 'ABANDERADO', 'Portador o portadora de la bandera', false, true, false, false, 34, 0.50],
            ['sargento', 'Sargento', 'SARGENTO', 'Responsable de formación o desfile', true, false, false, false, 35, 0.50],
            ['cabo_escuadra', 'Cabo de Escuadra', 'CABO_ESCUADRA', 'Responsable de escuadra o fila', true, false, false, false, 36, 0.25],

            // =========================
            // COMPARSAS / INFANTILES
            // =========================
            ['capitan_infantil', 'Capitán Infantil', 'CAPITAN_INFANTIL', 'Cargo festero representativo infantil', false, true, true, true, 40, 0.50],
            ['alferez_infantil', 'Alférez Infantil', 'ALFEREZ_INFANTIL', 'Cargo festero representativo infantil', false, true, true, true, 41, 0.50],
            ['abanderado_infantil', 'Abanderado/a Infantil', 'ABANDERADO_INFANTIL', 'Portador o portadora infantil de la bandera', false, true, true, true, 42, 0.25],
        ];

        $repository = $manager->getRepository(CargoMaster::class);

        foreach ($masters as [
                 $ref,
                 $nombre,
                 $codigo,
                 $descripcion,
                 $directivo,
                 $representativo,
                 $infantil,
                 $infantilEspecial,
                 $orden,
                 $anios
        ]) {
            $cargoMaster = $repository->findOneBy(['codigo' => $codigo]);

            if (!$cargoMaster instanceof CargoMaster) {
                $cargoMaster = new CargoMaster();
            }

            $cargoMaster->setNombre($nombre);
            $cargoMaster->setCodigo($codigo);
            $cargoMaster->setDescripcion($descripcion);
            $cargoMaster->setComputaComoDirectivo($directivo);
            $cargoMaster->setEsRepresentativo($representativo);
            $cargoMaster->setEsInfantil($infantil);
            $cargoMaster->setInfantilEspecial($infantilEspecial);
            $cargoMaster->setOrdenJerarquico($orden);
            $cargoMaster->setAniosComputables($anios);
            $cargoMaster->setActivo(true);

            $manager->persist($cargoMaster);
            $this->addReference('cargo_master.' . $ref, $cargoMaster);
        }

        $manager->flush();
    }
}
