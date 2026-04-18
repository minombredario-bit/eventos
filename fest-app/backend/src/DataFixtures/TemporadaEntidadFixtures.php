<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Entidad;
use App\Entity\TemporadaEntidad;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class TemporadaEntidadFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var Entidad $entidad */
        $entidad = $this->getReference('entidad.demo', Entidad::class);

        $temporadas = [
            ['2024', 'Temporada 2024', '2024-01-01', '2024-12-31', true],
            ['2025', 'Temporada 2025', '2025-01-01', '2025-12-31', false],
        ];

        foreach ($temporadas as [$codigo, $nombre, $inicio, $fin, $cerrada]) {
            $temporada = $manager->getRepository(TemporadaEntidad::class)->findOneBy([
                'entidad' => $entidad,
                'codigo' => $codigo,
            ]);

            if (!$temporada instanceof TemporadaEntidad) {
                $temporada = new TemporadaEntidad();
            }

            $temporada->setEntidad($entidad);
            $temporada->setCodigo($codigo);
            $temporada->setNombre($nombre);
            $temporada->setFechaInicio(new \DateTimeImmutable($inicio));
            $temporada->setFechaFin(new \DateTimeImmutable($fin));
            $temporada->setCerrada($cerrada);

            $manager->persist($temporada);
            $this->addReference('temporada_entidad.' . $codigo, $temporada);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }
}

