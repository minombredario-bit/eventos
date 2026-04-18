<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Entidad;
use App\Entity\Reconocimiento;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class ReconocimientoFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var Entidad $entidad */
        $entidad = $this->getReference('entidad.demo', Entidad::class);

        $reconocimientos = [
            ['BUNYOL_COURE', 'Bunyol de Coure', Reconocimiento::TIPO_ANTIGUEDAD, 1, 5.00, null, false, false],
            ['BUNYOL_ARGENT', 'Bunyol d\'Argent', Reconocimiento::TIPO_ANTIGUEDAD, 2, 10.00, null, false, true],
            ['BUNYOL_OR', 'Bunyol d\'Or', Reconocimiento::TIPO_ANTIGUEDAD, 3, 15.00, null, false, true],
            ['BUNYOL_OR_FULLES_LLORER', 'Bunyol d\'Or amb Fulles de Llorer', Reconocimiento::TIPO_ANTIGUEDAD, 4, 20.00, null, false, true],
            ['DISTINCION_DIRECTIVA', 'Distinció Directiva', Reconocimiento::TIPO_DIRECTIVO, 100, null, 3.00, true, false],
            ['DISTINTIU_COURE', 'Distintiu de Coure', Reconocimiento::TIPO_INFANTIL, 201, 3.00, null, false, false],
            ['DISTINTIU_ARGENT', 'Distintiu d\'Argent', Reconocimiento::TIPO_INFANTIL, 202, 5.00, null, false, true],
            ['DISTINTIU_OR', 'Distintiu d\'Or', Reconocimiento::TIPO_INFANTIL, 203, 8.00, null, false, true],
            ['DISTINCIO_PRESIDENT_INFANTIL', 'Distinció President Infantil', Reconocimiento::TIPO_INFANTIL, 210, 2.00, null, false, false],
            ['DISTINCIO_FALLERA_MAJOR_INFANTIL', 'Distinció Fallera Major Infantil', Reconocimiento::TIPO_INFANTIL, 211, 2.00, null, false, false],
        ];

        foreach ($reconocimientos as [$codigo, $nombre, $tipo, $orden, $minAntiguedad, $minAntiguedadDirectivo, $requiereDirectivo, $requiereAnterior]) {
            $reconocimiento = $manager->getRepository(Reconocimiento::class)->findOneBy([
                'entidad' => $entidad,
                'codigo' => $codigo,
            ]);

            if (!$reconocimiento instanceof Reconocimiento) {
                $reconocimiento = new Reconocimiento();
            }

            $reconocimiento->setEntidad($entidad);
            $reconocimiento->setCodigo($codigo);
            $reconocimiento->setNombre($nombre);
            $reconocimiento->setTipo($tipo);
            $reconocimiento->setOrden($orden);
            $reconocimiento->setMinAntiguedad($minAntiguedad);
            $reconocimiento->setMinAntiguedadDirectivo($minAntiguedadDirectivo);
            $reconocimiento->setRequiereDirectivo($requiereDirectivo);
            $reconocimiento->setRequiereAnterior($requiereAnterior);
            $reconocimiento->setActivo(true);
            $manager->persist($reconocimiento);
            $this->addReference('reconocimiento.' . strtolower($codigo), $reconocimiento);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }
}

