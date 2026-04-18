<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Entidad;
use App\Entity\Evento;
use App\Entity\ActividadEvento;
use App\Entity\Inscripcion;
use App\Entity\InscripcionLinea;
use App\Entity\Invitado;
use App\Entity\Usuario;
use App\Enum\EstadoValidacionEnum;
use App\Enum\EstadoEventoEnum;
use App\Enum\EstadoInscripcionEnum;
use App\Enum\EstadoPagoEnum;
use App\Enum\EstadoLineaInscripcionEnum;
use App\Enum\FranjaComidaEnum;
use App\Enum\CompatibilidadPersonaActividadEnum;
use App\Enum\MetodoPagoEnum;
use App\Enum\TipoPersonaEnum;
use App\Enum\TipoEventoEnum;
use App\Enum\TipoActividadEnum;
use App\Enum\TipoEntidadEnum;
use App\Entity\TipoEntidad as TipoEntidadEntity;
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
            // map enum value to TipoEntidad entity (fixtures create TipoEntidad rows from the enum)
            $tipoRepo = $this->em->getRepository(TipoEntidadEntity::class);
            $tipo = $tipoRepo->findOneBy(['codigo' => TipoEntidadEnum::COMPARSA->value]);
            if ($tipo) {
                $entidad->setTipoEntidad($tipo);
            }
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
            // En entornos de desarrollo/populate marcamos los usuarios como VALIDADOS
            // para poder iniciar sesión inmediatamente. Revertir en producción si procede.
            $usuario->setFechaNacimiento(null);

            $this->em->persist($usuario);
            $insertados++;
            $output->writeln("<info>Usuario creado: {$nombre} {$apellidos} ({$email})</info>");
        }

        $this->em->flush();
        $this->populateEventos($entidad, $output);
        $this->populateInscripciones($entidad, $output);
        $output->writeln("<info>Total de usuarios creados: {$insertados}</info>");
        return Command::SUCCESS;
    }

    private function populateEventos(Entidad $entidad, OutputInterface $output): void
    {
        $eventoRepo = $this->em->getRepository(Evento::class);
        $actividadRepo = $this->em->getRepository(ActividadEvento::class);
        $hoy = new \DateTimeImmutable('today');

        $eventosConfig = [
            [
                'slug' => 'evento-con-comida-y-con-invitados',
                'titulo' => 'Comida Popular con Invitados',
                'tipo' => TipoEventoEnum::COMIDA,
                'dias' => 7,
                'permiteInvitados' => true,
                'actividades' => [
                    ['nombre' => 'Actividad General', 'tipo' => TipoActividadEnum::LIBRE, 'franja' => FranjaComidaEnum::COMIDA, 'compat' => CompatibilidadPersonaActividadEnum::AMBOS, 'precio' => 12.0],
                ],
            ],
            [
                'slug' => 'evento-con-comida-sin-invitados',
                'titulo' => 'Cena de Socios (sin invitados)',
                'tipo' => TipoEventoEnum::CENA,
                'dias' => 12,
                'permiteInvitados' => false,
                'actividades' => [
                    ['nombre' => 'Actividad Cena Adulto', 'tipo' => TipoActividadEnum::ADULTO, 'franja' => FranjaComidaEnum::CENA, 'compat' => CompatibilidadPersonaActividadEnum::ADULTO, 'precio' => 18.0],
                    ['nombre' => 'Actividad Cena Infantil', 'tipo' => TipoActividadEnum::INFANTIL, 'franja' => FranjaComidaEnum::CENA, 'compat' => CompatibilidadPersonaActividadEnum::INFANTIL, 'precio' => 9.0],
                ],
            ],
            [
                'slug' => 'evento-sin-comida',
                'titulo' => 'Asamblea General (sin comida)',
                'tipo' => TipoEventoEnum::OTRO,
                'dias' => 16,
                'permiteInvitados' => false,
                'actividades' => [],
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

            foreach ($config['actividades'] as $orden => $actividadConfig) {
                /** @var ActividadEvento|null $actividad */
                $actividad = $actividadRepo->findOneBy(['evento' => $evento, 'nombre' => $actividadConfig['nombre']]);
                if ($actividad) {
                    continue;
                }

                $actividad = (new ActividadEvento())
                    ->setEvento($evento)
                    ->setNombre($actividadConfig['nombre'])
                    ->setDescripcion('Actividad demo autogenerado')
                    ->setTipoActividad($actividadConfig['tipo'])
                    ->setFranjaComida($actividadConfig['franja'])
                    ->setCompatibilidadPersona($actividadConfig['compat'])
                    ->setEsDePago(true)
                    ->setPrecioBase((float) $actividadConfig['precio'])
                    ->setPrecioAdultoInterno((float) $actividadConfig['precio'])
                    ->setPrecioAdultoExterno((float) $actividadConfig['precio'])
                    ->setPrecioInfantil((float) $actividadConfig['precio'])
                    ->setOrdenVisualizacion($orden + 1)
                    ->setActivo(true);

                $this->em->persist($actividad);
            }
        }

        $this->em->flush();
        $output->writeln("<info>Eventos demo preparados: {$creados} creados y sincronizados.</info>");
    }

    private function populateInscripciones(Entidad $entidad, OutputInterface $output): void
    {
        $usuarioRepo = $this->em->getRepository(Usuario::class);
        $eventoRepo = $this->em->getRepository(Evento::class);
        $actividadRepo = $this->em->getRepository(ActividadEvento::class);
        $inscripcionRepo = $this->em->getRepository(Inscripcion::class);
        $invitadoRepo = $this->em->getRepository(Invitado::class);

        /** @var Usuario|null $usuario */
        $usuario = $usuarioRepo->findOneBy(['email' => 'ana.gonzalez@example.com']);
        /** @var Evento|null $eventoConInvitados */
        $eventoConInvitados = $eventoRepo->findOneBy(['slug' => 'evento-con-comida-y-con-invitados']);
        /** @var Evento|null $eventoSinInvitados */
        $eventoSinInvitados = $eventoRepo->findOneBy(['slug' => 'evento-con-comida-sin-invitados']);

        if (!$usuario || !$eventoConInvitados || !$eventoSinInvitados) {
            $output->writeln('<comment>No se han podido preparar inscripciones demo: faltan usuario o eventos de referencia.</comment>');
            return;
        }

        /** @var ActividadEvento|null $actividadConInvitados */
        $actividadConInvitados = $actividadRepo->findOneBy([
            'evento' => $eventoConInvitados,
            'nombre' => 'Actividad General',
        ]);
        /** @var ActividadEvento|null $actividadSinInvitados */
        $actividadSinInvitados = $actividadRepo->findOneBy([
            'evento' => $eventoSinInvitados,
            'nombre' => 'Actividad Cena Adulto',
        ]);

        if (!$actividadConInvitados || !$actividadSinInvitados) {
            $output->writeln('<comment>No se han podido preparar inscripciones demo: faltan actividades de referencia.</comment>');
            return;
        }

        /** @var Invitado|null $invitado */
        $invitado = $invitadoRepo->findOneBy([
            'evento' => $eventoConInvitados,
            'creadoPor' => $usuario,
            'nombre' => 'Invitado',
            'apellidos' => 'Demo',
        ]);
        if (!$invitado) {
            $invitado = (new Invitado())
                ->setEvento($eventoConInvitados)
                ->setCreadoPor($usuario)
                ->setNombre('Invitado')
                ->setApellidos('Demo')
                ->setTipoPersona(TipoPersonaEnum::ADULTO)
                ->setObservaciones('Invitado demo para pruebas de detalle y menús/actividades.');
            $this->em->persist($invitado);
        }

        /** @var Inscripcion|null $inscripcionPagada */
        $inscripcionPagada = $inscripcionRepo->findOneBy([
            'evento' => $eventoConInvitados,
            'usuario' => $usuario,
            'codigo' => 'INSC-DEMO-PAGADA',
        ]);
        if (!$inscripcionPagada) {
            $inscripcionPagada = (new Inscripcion())
                ->setCodigo('INSC-DEMO-PAGADA')
                ->setEntidad($entidad)
                ->setEvento($eventoConInvitados)
                ->setUsuario($usuario)
                ->setEstadoInscripcion(EstadoInscripcionEnum::CONFIRMADA)
                ->setMetodoPago(MetodoPagoEnum::BIZUM)
                ->setReferenciaPago('BIZUM-DEMO-001')
                ->setFechaPago(new \DateTimeImmutable('-1 hour'));

            $lineaTitular = (new InscripcionLinea())
                ->setInscripcion($inscripcionPagada)
                ->setUsuario($usuario)
                ->setActividad($actividadConInvitados)
                ->setPrecioUnitario(12.0)
                ->setEstadoLinea(EstadoLineaInscripcionEnum::CONFIRMADA)
                ->setPagada(true);
            $lineaTitular->crearSnapshot();
            $inscripcionPagada->addLinea($lineaTitular);

            $lineaInvitado = (new InscripcionLinea())
                ->setInscripcion($inscripcionPagada)
                ->setInvitado($invitado)
                ->setActividad($actividadConInvitados)
                ->setPrecioUnitario(12.0)
                ->setEstadoLinea(EstadoLineaInscripcionEnum::CONFIRMADA)
                ->setPagada(true);
            $lineaInvitado->crearSnapshot();
            $inscripcionPagada->addLinea($lineaInvitado);

            $inscripcionPagada->setImporteTotal(24.0);
            $inscripcionPagada->setImportePagado(24.0);
            $inscripcionPagada->setEstadoPago(EstadoPagoEnum::PAGADO);
            $this->em->persist($inscripcionPagada);
        }

        /** @var Inscripcion|null $inscripcionPendiente */
        $inscripcionPendiente = $inscripcionRepo->findOneBy([
            'evento' => $eventoSinInvitados,
            'usuario' => $usuario,
            'codigo' => 'INSC-DEMO-PENDIENTE',
        ]);
        if (!$inscripcionPendiente) {
            $inscripcionPendiente = (new Inscripcion())
                ->setCodigo('INSC-DEMO-PENDIENTE')
                ->setEntidad($entidad)
                ->setEvento($eventoSinInvitados)
                ->setUsuario($usuario)
                ->setEstadoInscripcion(EstadoInscripcionEnum::CONFIRMADA)
                ->setEstadoPago(EstadoPagoEnum::PENDIENTE);

            $lineaPendiente = (new InscripcionLinea())
                ->setInscripcion($inscripcionPendiente)
                ->setUsuario($usuario)
                ->setActividad($actividadSinInvitados)
                ->setPrecioUnitario(18.0)
                ->setEstadoLinea(EstadoLineaInscripcionEnum::PENDIENTE)
                ->setPagada(false);
            $lineaPendiente->crearSnapshot();
            $inscripcionPendiente->addLinea($lineaPendiente);

            $inscripcionPendiente->setImporteTotal(18.0);
            $inscripcionPendiente->setImportePagado(0.0);
            $this->em->persist($inscripcionPendiente);
        }

        $this->em->flush();
        $output->writeln('<info>Inscripciones demo preparadas (pagada y pendiente) con líneas asociadas.</info>');
    }
}

