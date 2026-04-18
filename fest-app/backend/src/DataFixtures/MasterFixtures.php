<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Orquestador de carga para asegurar un orden estable de fixtures.
 *
 * Doctrine carga primero todas las dependencias declaradas aquí y deja este
 * fixture como último nodo de la cadena.
 */
final class MasterFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Fixture de orquestación: no inserta datos propios.
    }

    public function getDependencies(): array
    {
        return [
            TipoEntidadFixtures::class,
            CargoMasterFixtures::class,
            AppFixtures::class,
            CargoFixtures::class,
            ReconocimientoFixtures::class,
            TipoEntidadCargoFixtures::class,
            TemporadaEntidadFixtures::class,
            EntidadCargoFixtures::class,
            UsuarioTemporadaCargoFixtures::class,
            UsuarioReconocimientoFixtures::class,
            PagoFixtures::class,
            ColaCorreoFixtures::class,
        ];
    }
}

