<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Entidad;
use App\Entity\Usuario;
use App\Enum\EstadoValidacionEnum;
use App\Enum\TipoEntidadEnum;
use App\Enum\TipoRelacionEconomicaEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PopulateUsuariosCommand extends Command
{
    protected static $defaultName = 'app:populate-usuarios';
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    protected function configure(): void
    {
        $this->setDescription('Create 3 usuarios de prueba vinculados a una Entidad (si no existe, la crea).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Asegurar una Entidad de prueba
        $entidadRepo = $this->em->getRepository(Entidad::class);
        /** @var Entidad|null $entidad */
        $entidad = $entidadRepo->findOneBy([], ['id' => 'ASC']);
        if (!$entidad) {
            $entidad = new Entidad();
            $entidad->setNombre('Entidad de Prueba');
            $entidad->setSlug('entidad-prueba');
            $entidad->setTipoEntidad(TipoEntidadEnum::COMPARSA);
            $entidad->setEmailContacto('contacto@prueba.local');
            $entidad->setCodigoRegistro('ENTPRUEBA');
            $entidad->setTemporadaActual('2026');
            $entidad->setActiva(true);
            $this->em->persist($entidad);
            $this->em->flush();
            $output->writeln('<info>Entidad de prueba creada</info>');
        }

        // Crear 3 usuarios de prueba
        $usuariosData = [
            ['Ana', 'Gonzalez', 'ana.gonzalez@example.com', 'password'],
            ['Luis', 'Martinez', 'luis.martinez@example.com', 'password'],
            ['Sofía', 'Perez', 'sofia.perez@example.com', 'password'],
        ];

        $insertados = 0;
        foreach ($usuariosData as $idx => $ud) {
            [$nombre, $apellidos, $email, $pass] = $ud;
            // Evita duplicados por email
            $repo = $this->em->getRepository(Usuario::class);
            $existing = $repo->findOneBy(['email' => $email]);
            if ($existing) {
                $output->writeln("<comment>Usuario existente con email $email; saltando.</comment>");
                continue;
            }

            $usuario = new Usuario();
            $usuario->setEntidad($entidad);
            $usuario->setNombre($nombre);
            $usuario->setApellidos($apellidos);
            $usuario->setEmail($email);
            // Hash de contraseña básica
            $usuario->setPassword(password_hash($pass, PASSWORD_BCRYPT));
            $usuario->setActivo(true);
            $usuario->setTipoUsuarioEconomico(TipoRelacionEconomicaEnum::INTERNO);
            // En entornos de desarrollo/populate marcamos los usuarios como VALIDADOS
            // para poder iniciar sesión inmediatamente. Revertir en producción si procede.
            $usuario->setEstadoValidacion(EstadoValidacionEnum::VALIDADO);
            $usuario->setFechaValidacion(new \DateTimeImmutable());
            $usuario->setFechaNacimiento(null);

            $this->em->persist($usuario);
            $insertados++;
            $output->writeln("<info>Usuario creado: {$nombre} {$apellidos} ({$email})</info>");
        }

        $this->em->flush();
        $output->writeln("<info>Total de usuarios creados: {$insertados}</info>");
        return Command::SUCCESS;
    }
}

