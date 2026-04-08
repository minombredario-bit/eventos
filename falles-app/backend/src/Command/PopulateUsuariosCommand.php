<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Entidad;
use App\Entity\Evento;
use App\Entity\MenuEvento;
use App\Entity\Usuario;
use App\Enum\EstadoValidacionEnum;
use App\Enum\EstadoEventoEnum;
use App\Enum\FranjaComidaEnum;
use App\Enum\CompatibilidadPersonaMenuEnum;
use App\Enum\TipoEventoEnum;
use App\Enum\TipoMenuEnum;
use App\Enum\TipoEntidadEnum;
use App\Enum\TipoRelacionEconomicaEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:populate-usuarios',
    description: 'Create 3 usuarios de prueba vinculados a una Entidad (si no existe, la crea).'
)]
class PopulateUsuariosCommand extends Command
{
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
        $this->populateEventos($entidad, $output);
        $output->writeln("<info>Total de usuarios creados: {$insertados}</info>");
        return Command::SUCCESS;
    }

    private function populateEventos(Entidad $entidad, OutputInterface $output): void
    {
        $eventoRepo = $this->em->getRepository(Evento::class);
        $menuRepo = $this->em->getRepository(MenuEvento::class);
        $hoy = new \DateTimeImmutable('today');

        $eventosConfig = [
            [
                'slug' => 'evento-con-comida-y-con-invitados',
                'titulo' => 'Comida Popular con Invitados',
                'tipo' => TipoEventoEnum::COMIDA,
                'dias' => 7,
                'permiteInvitados' => true,
                'menus' => [
                    ['nombre' => 'Menu General', 'tipo' => TipoMenuEnum::LIBRE, 'franja' => FranjaComidaEnum::COMIDA, 'compat' => CompatibilidadPersonaMenuEnum::AMBOS, 'precio' => 12.0],
                ],
            ],
            [
                'slug' => 'evento-con-comida-sin-invitados',
                'titulo' => 'Cena de Socios (sin invitados)',
                'tipo' => TipoEventoEnum::CENA,
                'dias' => 12,
                'permiteInvitados' => false,
                'menus' => [
                    ['nombre' => 'Menu Cena Adulto', 'tipo' => TipoMenuEnum::ADULTO, 'franja' => FranjaComidaEnum::CENA, 'compat' => CompatibilidadPersonaMenuEnum::ADULTO, 'precio' => 18.0],
                    ['nombre' => 'Menu Cena Infantil', 'tipo' => TipoMenuEnum::INFANTIL, 'franja' => FranjaComidaEnum::CENA, 'compat' => CompatibilidadPersonaMenuEnum::INFANTIL, 'precio' => 9.0],
                ],
            ],
            [
                'slug' => 'evento-sin-comida',
                'titulo' => 'Asamblea General (sin comida)',
                'tipo' => TipoEventoEnum::OTRO,
                'dias' => 16,
                'permiteInvitados' => false,
                'menus' => [],
            ],
        ];

        $creados = 0;
        foreach ($eventosConfig as $config) {
            /** @var Evento|null $evento */
            $evento = $eventoRepo->findOneBy(['slug' => $config['slug']]);
            if (!$evento) {
                $fecha = $hoy->modify(sprintf('+%d days', $config['dias']));
                $evento = (new Evento())
                    ->setEntidad($entidad)
                    ->setTitulo($config['titulo'])
                    ->setSlug($config['slug'])
                    ->setDescripcion('Evento demo generado automáticamente para pruebas de inscripción e invitados.')
                    ->setTipoEvento($config['tipo'])
                    ->setFechaEvento($fecha)
                    ->setHoraInicio(new \DateTimeImmutable('14:00'))
                    ->setHoraFin(new \DateTimeImmutable('17:00'))
                    ->setLugar('Casal de prueba')
                    ->setFechaInicioInscripcion($fecha->modify('-10 days')->setTime(9, 0))
                    ->setFechaFinInscripcion($fecha->modify('-1 day')->setTime(23, 0))
                    ->setVisible(true)
                    ->setPublicado(true)
                    ->setAdmitePago(true)
                    ->setEstado(EstadoEventoEnum::PUBLICADO)
                    ->setPermiteInvitados((bool) $config['permiteInvitados']);
                $this->em->persist($evento);
                $creados++;
            } else {
                $evento->setPermiteInvitados((bool) $config['permiteInvitados']);
            }

            foreach ($config['menus'] as $orden => $menuConfig) {
                /** @var MenuEvento|null $menu */
                $menu = $menuRepo->findOneBy(['evento' => $evento, 'nombre' => $menuConfig['nombre']]);
                if ($menu) {
                    continue;
                }

                $menu = (new MenuEvento())
                    ->setEvento($evento)
                    ->setNombre($menuConfig['nombre'])
                    ->setDescripcion('Menu demo autogenerado')
                    ->setTipoMenu($menuConfig['tipo'])
                    ->setFranjaComida($menuConfig['franja'])
                    ->setCompatibilidadPersona($menuConfig['compat'])
                    ->setEsDePago(true)
                    ->setPrecioBase((float) $menuConfig['precio'])
                    ->setPrecioAdultoInterno((float) $menuConfig['precio'])
                    ->setPrecioAdultoExterno((float) $menuConfig['precio'])
                    ->setPrecioInfantil((float) $menuConfig['precio'])
                    ->setOrdenVisualizacion($orden + 1)
                    ->setActivo(true);

                $this->em->persist($menu);
            }
        }

        $this->em->flush();
        $output->writeln("<info>Eventos demo preparados: {$creados} creados y sincronizados.</info>");
    }
}

