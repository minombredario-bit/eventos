<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ColaCorreo;
use App\Entity\Entidad;
use App\Entity\Usuario;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class ColaCorreoFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var Entidad $entidad */
        $entidad = $this->getRepositoryReference($manager, Entidad::class, ['slug' => 'falla-llibre-joan-lloren-25']);
        /** @var Usuario $admin */
        $admin = $this->getRepositoryReference($manager, Usuario::class, ['email' => 'admin@fallallobre.es']);
        /** @var Usuario $usuario */
        $usuario = $this->getRepositoryReference($manager, Usuario::class, ['email' => 'juan.perez@example.com']);

        $correos = [
            [
                'ana.rodriguez@example.com',
                'Validación pendiente de usuario',
                'email/validacion_usuario.html.twig',
                ['usuario' => 'Ana Rodríguez'],
                ColaCorreo::ESTADO_PENDIENTE,
                0,
                null,
                null,
            ],
            [
                'juan.perez@example.com',
                'Recordatorio de pago de inscripción',
                'recordatorio_pago',
                ['inscripcion' => 'INSC-DEMO-001', 'importePendiente' => 0.00],
                ColaCorreo::ESTADO_ENVIADO,
                1,
                null,
                new \DateTimeImmutable('2025-04-10 09:30:00'),
            ],
            [
                'info@fallallobre.es',
                'Error al enviar resumen semanal',
                'email/resumen_semanal.html.twig',
                ['intento' => 2, 'destino' => 'comision'],
                ColaCorreo::ESTADO_ERROR,
                2,
                'SMTP temporalmente no disponible',
                null,
            ],
        ];

        foreach ($correos as [$destinatario, $asunto, $plantilla, $contexto, $estado, $intentos, $ultimoError, $enviadoAt]) {
            $correo = $manager->getRepository(ColaCorreo::class)->findOneBy([
                'destinatario' => strtolower($destinatario),
                'plantilla' => $plantilla,
            ]);

            if (!$correo instanceof ColaCorreo) {
                $correo = new ColaCorreo();
            }

            $correo->setEntidad($entidad);
            $correo->setUsuario(match ($destinatario) {
                'juan.perez@example.com' => $usuario,
                'ana.rodriguez@example.com' => $admin,
                default => null,
            });
            $correo->setDestinatario($destinatario);
            $correo->setAsunto($asunto);
            $correo->setPlantilla($plantilla);
            $correo->setContexto($contexto);
            $correo->setEstado($estado);

            while ($correo->getIntentos() < $intentos) {
                $correo->incrementarIntentos();
            }

            $correo->setUltimoError($ultimoError);
            $correo->setEnviadoAt($enviadoAt);

            $manager->persist($correo);
            $this->addReference('cola_correo.' . $plantilla . '.' . strtolower(str_replace('@', '_', $destinatario)), $correo);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }

    private function getRepositoryReference(ObjectManager $manager, string $className, array $criteria): object
    {
        $entity = $manager->getRepository($className)->findOneBy($criteria);

        if (!is_object($entity)) {
            throw new \RuntimeException(sprintf('No se encontró la entidad %s con criterios %s.', $className, json_encode($criteria)));
        }

        return $entity;
    }
}

