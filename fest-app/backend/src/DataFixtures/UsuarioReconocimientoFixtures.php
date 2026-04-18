<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Reconocimiento;
use App\Entity\Usuario;
use App\Entity\UsuarioReconocimiento;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class UsuarioReconocimientoFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $concesiones = [
            ['juan.perez@example.com', 'reconocimiento.bunyol_coure', '2024', 'Entrega de ejemplo para socio consolidado'],
            ['admin@fallallobre.es', 'reconocimiento.distincion_directiva', '2024', 'Reconocimiento al trabajo directivo'],
            ['elena.perez@example.com', 'reconocimiento.bunyol_argent', '2025', 'Reconocimiento de temporada 2025'],
            ['pablo.perez@example.com', 'reconocimiento.distintiu_coure', '2025', 'Reconocimiento infantil de ejemplo'],
        ];

        foreach ($concesiones as [$email, $reconocimientoRef, $temporadaCodigo, $observaciones]) {
            /** @var Usuario $usuario */
            $usuario = $manager->getRepository(Usuario::class)->findOneBy(['email' => $email]);
            /** @var Reconocimiento $reconocimiento */
            $reconocimiento = $this->getReference($reconocimientoRef, Reconocimiento::class);

            if (!$usuario instanceof Usuario) {
                continue;
            }

            $usuarioReconocimiento = $manager->getRepository(UsuarioReconocimiento::class)->findOneBy([
                'usuario' => $usuario,
                'reconocimiento' => $reconocimiento,
            ]);

            if (!$usuarioReconocimiento instanceof UsuarioReconocimiento) {
                $usuarioReconocimiento = new UsuarioReconocimiento();
            }

            $usuarioReconocimiento->setUsuario($usuario);
            $usuarioReconocimiento->setEntidad($usuario->getEntidad());
            $usuarioReconocimiento->setReconocimiento($reconocimiento);
            $usuarioReconocimiento->setTemporadaCodigo($temporadaCodigo);
            $usuarioReconocimiento->setFechaConcesion(new \DateTimeImmutable(sprintf('%s-03-15', $temporadaCodigo)));
            $usuarioReconocimiento->setObservaciones($observaciones);

            $manager->persist($usuarioReconocimiento);
            $this->addReference(sprintf('usuario_reconocimiento.%s.%s', strtolower(str_replace('@', '_', $email)), strtolower($reconocimientoRef)), $usuarioReconocimiento);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class, ReconocimientoFixtures::class];
    }
}

