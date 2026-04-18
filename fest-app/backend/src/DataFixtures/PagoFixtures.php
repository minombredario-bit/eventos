<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Inscripcion;
use App\Entity\Pago;
use App\Entity\Usuario;
use App\Enum\MetodoPagoEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class PagoFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var Usuario $registrador */
        $registrador = $manager->getRepository(Usuario::class)->findOneBy(['email' => 'admin@fallallobre.es']);

        $pagos = [
            ['INSC-DEMO-001', 'PAGO-DEMO-001', 40.00, MetodoPagoEnum::BIZUM, 'Pago completo de la cena de gala'],
            ['INSC-DEMO-002', 'PAGO-DEMO-002', 17.00, MetodoPagoEnum::EFECTIVO, 'Pago completo de la comida familiar'],
        ];

        foreach ($pagos as [$codigoInscripcion, $referencia, $importe, $metodoPago, $observaciones]) {
            /** @var Inscripcion $inscripcion */
            $inscripcion = $manager->getRepository(Inscripcion::class)->findOneBy(['codigo' => $codigoInscripcion]);

            if (!$inscripcion instanceof Inscripcion || !$registrador instanceof Usuario) {
                continue;
            }

            $pago = $manager->getRepository(Pago::class)->findOneBy(['referencia' => $referencia]);

            if (!$pago instanceof Pago) {
                $pago = new Pago();
            }

            $pago->setInscripcion($inscripcion);
            $pago->setFecha(new \DateTimeImmutable(sprintf('%s 12:00:00', $inscripcion->getEvento()->getFechaEvento()->format('Y-m-d'))));
            $pago->setImporte($importe);
            $pago->setMetodoPago($metodoPago);
            $pago->setReferencia($referencia);
            $pago->setEstado('confirmado');
            $pago->setObservaciones($observaciones);
            $pago->setRegistradoPor($registrador);

            $manager->persist($pago);
            $this->addReference('pago.' . strtolower($referencia), $pago);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }
}

