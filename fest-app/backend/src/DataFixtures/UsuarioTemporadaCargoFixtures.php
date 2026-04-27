<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\EntidadCargo;
use App\Entity\Cargo;
use App\Entity\CargoMaster;
use App\Entity\TemporadaEntidad;
use App\Entity\Usuario;
use App\Entity\UsuarioTemporadaCargo;
use App\Enum\TipoPersonaEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class UsuarioTemporadaCargoFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var TemporadaEntidad $temporada2025 */
        $temporada2025 = $this->getReference('temporada_entidad.2025', TemporadaEntidad::class);

        $asignaciones = [
            ['juan.perez@example.com', 'cargo.presidente', true, true, true, 1.00, TipoPersonaEnum::ADULTO, 1, 'Presidencia de la temporada 2025'],
            ['admin@fallallobre.es', 'cargo.vocal_apoyo', false, true, true, 0.00, TipoPersonaEnum::ADULTO, 20, 'Apoyo organizativo de la temporada 2025'],
            ['elena.perez@example.com', 'cargo.fallera_mayor', false, true, true, 1.00, TipoPersonaEnum::ADULTO, 10, 'Cargo representativo de la temporada 2025'],
            ['pablo.perez@example.com', 'cargo.vocal_infantil_apoyo', false, true, false, 0.00, TipoPersonaEnum::INFANTIL, 21, 'Apoyo infantil de la temporada 2025'],
        ];

        foreach ($asignaciones as [$email, $cargoRef, $principal, $computaAntiguedad, $computaReconocimiento, $aniosExtra, $tipoPersona, $orden, $observaciones]) {
            /** @var Usuario $usuario */
            $usuario = $manager->getRepository(Usuario::class)->findOneBy(['email' => $email]);
            /** @var Cargo $cargo */
            $cargo = $this->getReference($cargoRef, Cargo::class);

            // Determinar la entidad a la que pertenece el usuario (fixtures crean usuarios con entidad ya seteada)
            $entidad = $usuario->getEntidad();

            // Buscar la EntidadCargo previamente creada por migración/fixtures.
            // Prioridad: cargo interno (Entidad->Cargo) — si no existe, buscar por CargoMaster (oficial).
            $entidadCargo = $manager->getRepository(EntidadCargo::class)->findOneBy([
                'entidad' => $entidad,
                'cargo' => $cargo,
            ]);

            if (!$entidadCargo instanceof EntidadCargo) {
                // Intentar resolver un CargoMaster equivalente al código del Cargo (case-insensitive)
                $codigoCargo = $cargo->getCodigo();
                $cargoMaster = null;

                if (null !== $codigoCargo) {
                    $cargoMaster = $manager->getRepository(CargoMaster::class)->findOneBy(['codigo' => $codigoCargo]);
                    if (!$cargoMaster instanceof CargoMaster) {
                        $cargoMaster = $manager->getRepository(CargoMaster::class)->findOneBy(['codigo' => strtolower($codigoCargo)]);
                    }
                    if (!$cargoMaster instanceof CargoMaster) {
                        $cargoMaster = $manager->getRepository(CargoMaster::class)->findOneBy(['codigo' => strtoupper($codigoCargo)]);
                    }
                }

                if ($cargoMaster instanceof CargoMaster) {
                    $entidadCargo = $manager->getRepository(EntidadCargo::class)->findOneBy([
                        'entidad' => $entidad,
                        'cargoMaster' => $cargoMaster,
                    ]);
                }
            }

            if (!$entidadCargo instanceof EntidadCargo) {
                throw new \RuntimeException(sprintf(
                    'EntidadCargo no encontrada para entidad "%s" y cargo "%s". Asegúrate de ejecutar la migración que crea los cargos oficiales o de cargar las fixtures de `EntidadCargo`.',
                    $entidad->getNombre(),
                    $cargo->getCodigo() ?? $cargo->getId()
                ));
            }

            if (!$usuario instanceof Usuario) {
                continue;
            }

            $usuarioTemporadaCargo = $manager->getRepository(UsuarioTemporadaCargo::class)->findOneBy([
                'usuario' => $usuario,
                'temporada' => $temporada2025,
                'entidadCargo' => $entidadCargo,
            ]);

            if (!$usuarioTemporadaCargo instanceof UsuarioTemporadaCargo) {
                $usuarioTemporadaCargo = new UsuarioTemporadaCargo();
            }

            $usuarioTemporadaCargo->setUsuario($usuario);
            $usuarioTemporadaCargo->setTemporada($temporada2025);
            $usuarioTemporadaCargo->setEntidadCargo($entidadCargo);
            $usuarioTemporadaCargo->setPrincipal($principal);
            $usuarioTemporadaCargo->setComputaAntiguedad($computaAntiguedad);
            $usuarioTemporadaCargo->setComputaReconocimiento($computaReconocimiento);
            $usuarioTemporadaCargo->setAniosExtraAplicados($aniosExtra);
            $usuarioTemporadaCargo->setTipoPersona($tipoPersona);
            $usuarioTemporadaCargo->setOrden($orden);
            $usuarioTemporadaCargo->setObservaciones($observaciones);

            $manager->persist($usuarioTemporadaCargo);
            $this->addReference(sprintf('usuario_temporada_cargo.%s.%s', strtolower(str_replace('@', '_', $email)), strtolower(str_replace('cargo.', '', $cargoRef))), $usuarioTemporadaCargo);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class, CargoFixtures::class, EntidadCargoFixtures::class, TemporadaEntidadFixtures::class];
    }
}

