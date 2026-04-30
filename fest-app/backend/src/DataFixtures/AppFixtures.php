<?php

namespace App\DataFixtures;

use App\Entity\Entidad;
use App\Entity\Usuario;
use App\Entity\Evento;
use App\Entity\ActividadEvento;
use App\Entity\RelacionUsuario;
use App\Entity\Invitado;
use App\Entity\Inscripcion;
use App\Entity\InscripcionLinea;
use App\Entity\SeleccionParticipanteEvento;
use App\Entity\SeleccionParticipanteEventoLinea;
use App\Enum\TipoEntidadEnum;
use App\Enum\TipoRelacionEconomicaEnum;
use App\Enum\TipoRelacionEnum;
use App\Enum\CensadoViaEnum;
use App\Enum\EstadoValidacionEnum;
use App\Enum\TipoEventoEnum;
use App\Enum\EstadoEventoEnum;
use App\Enum\FranjaComidaEnum;
use App\Enum\CompatibilidadPersonaActividadEnum;
use App\Enum\TipoActividadEnum;
use App\Enum\TipoPersonaEnum;
use App\Enum\EstadoInscripcionEnum;
use App\Enum\EstadoPagoEnum;
use App\Enum\EstadoLineaInscripcionEnum;
use App\Enum\MetodoPagoEnum;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // ============================================
        // 1. Crear Entidad de prueba
        // ============================================
        $entidad = new Entidad();
        $entidad->setNombre('Falla LlibreJoan Lloren 25');
        $entidad->setSlug('falla-llibre-joan-lloren-25');
        $entidad->setDescripcion('Falla histórica del barrio de Russafa, Valencia');
        /** @var \App\Entity\TipoEntidad $tipoEntidad */
        $tipoEntidad = $this->getReference('tipo_entidad.falla', \App\Entity\TipoEntidad::class);
        $entidad->setTipoEntidad($tipoEntidad);
        $entidad->setTerminologiaSocio('faller/a');
        $entidad->setTerminologiaEvento('mascletà');
        $entidad->setEmailContacto('info@fallallobre.es');
        $entidad->setTelefono('963456789');
        $entidad->setDireccion('C/ Joan Lloren, 25 - 46009 Valencia');
        $entidad->setCodigoRegistro('FALLA2024');
        $entidad->setTemporadaActual('2024');
        $entidad->setTextoLopd('LOPD');
        $entidad->setTemporadaInicioDia(20);
        $entidad->setTemporadaInicioMes(3);
        $entidad->setActiva(true);
        $manager->persist($entidad);

        // ============================================
        // 2. Crear Usuarios de prueba
        // ============================================

        // Superadmin (pertenece a la entidad para evitar FK issues)
        $superadmin = new Usuario();
        $superadmin->setEntidad($entidad);
        $superadmin->setNombre('Carlos');
        $superadmin->setApellidos('Martínez Super');
        $superadmin->setEmail('superadmin@example.com');
        $superadmin->setTelefono('600111222');
        $superadmin->setPassword($this->passwordHasher->hashPassword($superadmin, 'super123'));
        $superadmin->setRoles(['ROLE_SUPERADMIN']);
        $superadmin->setActivo(true);
        $superadmin->setTipoPersona(TipoPersonaEnum::ADULTO);
        $superadmin->setCensadoVia(CensadoViaEnum::MANUAL);
        $superadmin->setFechaAltaCenso(new \DateTimeImmutable('2023-01-15'));
        $manager->persist($superadmin);

        // Admin de entidad
        $adminEntidad = new Usuario();
        $adminEntidad->setEntidad($entidad);
        $adminEntidad->setNombre('María');
        $adminEntidad->setApellidos('García Admin');
        $adminEntidad->setEmail('admin.demo@festapp.local');
        $adminEntidad->setTelefono('600222333');
        $adminEntidad->setPassword($this->passwordHasher->hashPassword($adminEntidad, 'admin123'));
        $adminEntidad->setRoles(['ROLE_ADMIN_ENTIDAD']);
        $adminEntidad->setActivo(true);
        $adminEntidad->setTipoPersona(TipoPersonaEnum::ADULTO);
        $adminEntidad->setCensadoVia(CensadoViaEnum::MANUAL);
        $adminEntidad->setFechaAltaCenso(new \DateTimeImmutable('2023-02-01'));
        $manager->persist($adminEntidad);

        // Usuario normal validado (con familiares)
        $usuario1 = new Usuario();
        $usuario1->setEntidad($entidad);
        $usuario1->setNombre('Juan');
        $usuario1->setApellidos('Pérez López');
        $usuario1->setEmail('juan.perez@example.com');
        $usuario1->setTelefono('600333444');
        $usuario1->setPassword($this->passwordHasher->hashPassword($usuario1, 'user123'));
        $usuario1->setRoles(['ROLE_USER']);
        $usuario1->setActivo(true);
        $usuario1->setTipoPersona(TipoPersonaEnum::ADULTO);
        $usuario1->setCensadoVia(CensadoViaEnum::EXCEL);
        $usuario1->setFechaAltaCenso(new \DateTimeImmutable('2024-01-12'));
        $manager->persist($usuario1);

        // Usuario pendiente de validación
        $usuario2 = new Usuario();
        $usuario2->setEntidad($entidad);
        $usuario2->setNombre('Ana');
        $usuario2->setApellidos('Rodríguez Sánchez');
        $usuario2->setEmail('ana.rodriguez@example.com');
        $usuario2->setTelefono('600444555');
        $usuario2->setPassword($this->passwordHasher->hashPassword($usuario2, 'user123'));
        $usuario2->setRoles(['ROLE_USER']);
        $usuario2->setActivo(true);
        $usuario2->setTipoPersona(TipoPersonaEnum::ADULTO);
        $usuario2->setCensadoVia(CensadoViaEnum::EXCEL);
        $manager->persist($usuario2);

        // ============================================
        // 3. Crear usuarios relacionados (modelo actual)
        // ============================================

        $relacionadoConyuge = new Usuario();
        $relacionadoConyuge->setEntidad($entidad);
        $relacionadoConyuge->setNombre('Elena');
        $relacionadoConyuge->setApellidos('Pérez García');
        $relacionadoConyuge->setEmail('elena.perez@example.com');
        $relacionadoConyuge->setTelefono('600555111');
        $relacionadoConyuge->setPassword($this->passwordHasher->hashPassword($relacionadoConyuge, 'user123'));
        $relacionadoConyuge->setRoles(['ROLE_USER']);
        $relacionadoConyuge->setActivo(true);
        $relacionadoConyuge->setTipoPersona(TipoPersonaEnum::ADULTO);
        $relacionadoConyuge->setCensadoVia(CensadoViaEnum::MANUAL);
        $relacionadoConyuge->setFechaNacimiento(new \DateTimeImmutable('1985-06-15'));
        $manager->persist($relacionadoConyuge);

        $relacionadoHijo = new Usuario();
        $relacionadoHijo->setEntidad($entidad);
        $relacionadoHijo->setNombre('Pablo');
        $relacionadoHijo->setApellidos('Pérez Pérez');
        $relacionadoHijo->setEmail('pablo.perez@example.com');
        $relacionadoHijo->setTelefono('600555222');
        $relacionadoHijo->setPassword($this->passwordHasher->hashPassword($relacionadoHijo, 'user123'));
        $relacionadoHijo->setRoles(['ROLE_USER']);
        $relacionadoHijo->setActivo(true);
        $relacionadoHijo->setTipoPersona(TipoPersonaEnum::INFANTIL);
        $relacionadoHijo->setCensadoVia(CensadoViaEnum::MANUAL);
        $relacionadoHijo->setFechaNacimiento(new \DateTimeImmutable('2015-03-20'));
        $manager->persist($relacionadoHijo);

        $relacionConyuge = new RelacionUsuario();
        $relacionConyuge->setUsuarioOrigen($usuario1);
        $relacionConyuge->setUsuarioDestino($relacionadoConyuge);
        $relacionConyuge->setTipoRelacion(TipoRelacionEnum::FAMILIAR);
        $manager->persist($relacionConyuge);

        $relacionHijo = new RelacionUsuario();
        $relacionHijo->setUsuarioOrigen($usuario1);
        $relacionHijo->setUsuarioDestino($relacionadoHijo);
        $relacionHijo->setTipoRelacion(TipoRelacionEnum::FAMILIAR);
        $manager->persist($relacionHijo);

        $hoy = new \DateTimeImmutable('today');

        $fechaEventoCena = $hoy->modify('+4 days');
        $fechaEventoComida = $hoy->modify('+10 days');
        $fechaEventoMerienda = $hoy->modify('+16 days');
        $fechaEventoDemo = $hoy->modify('+22 days');
        $fechaEventoSinComida = $hoy->modify('+28 days');
        $fechaEventoBorrador = $hoy->modify('+35 days');

        // ============================================
        // 4. Crear Eventos
        // ============================================

        // Evento 1: Cena (próximo, para probar adulto + ambos)
        $evento1 = new Evento();
        $evento1->setEntidad($entidad);
        $evento1->setTitulo('Cena de Gala de Primavera');
        $evento1->setSlug('cena-gala-primavera-fixtures');
        $evento1->setDescripcion('Evento de prueba cercano para detalle de evento y selección de menús en franja CENA.');
        $evento1->setTipoEvento(TipoEventoEnum::CENA);
        $evento1->setFechaEvento($fechaEventoCena);
        $evento1->setHoraInicio(new \DateTimeImmutable('21:00'));
        $evento1->setHoraFin(new \DateTimeImmutable('23:30'));
        $evento1->setLugar('Casal principal - salón grande');
        $evento1->setAforo(80);
        $evento1->setFechaInicioInscripcion($fechaEventoCena->modify('-12 days')->setTime(10, 0));
        $evento1->setFechaFinInscripcion($fechaEventoCena->modify('-1 day')->setTime(23, 0));
        $evento1->setVisible(true);
        $evento1->setAdmitePago(true);
        $evento1->setPermiteInvitados(true);
        $evento1->setEstado(EstadoEventoEnum::PUBLICADO);
        $evento1->setRequiereVerificacionAcceso(false);
        $evento1->setCodigoVisual('CENA-TEST');
        $manager->persist($evento1);

        // Evento 2: Comida (próximo, para probar menú infantil)
        $evento2 = new Evento();
        $evento2->setEntidad($entidad);
        $evento2->setTitulo('Comida Familiar del Casal');
        $evento2->setSlug('comida-familiar-casal-fixtures');
        $evento2->setDescripcion('Evento de prueba con menú ambos e infantil para validar filtros por compatibilidad.');
        $evento2->setTipoEvento(TipoEventoEnum::COMIDA);
        $evento2->setFechaEvento($fechaEventoComida);
        $evento2->setHoraInicio(new \DateTimeImmutable('14:00'));
        $evento2->setHoraFin(new \DateTimeImmutable('16:30'));
        $evento2->setLugar('Patio central del casal');
        $evento2->setAforo(160);
        $evento2->setFechaInicioInscripcion($fechaEventoComida->modify('-14 days')->setTime(9, 0));
        $evento2->setFechaFinInscripcion($fechaEventoComida->modify('-1 day')->setTime(23, 0));
        $evento2->setVisible(true);
        $evento2->setAdmitePago(true);
        $evento2->setPermiteInvitados(true);
        $evento2->setEstado(EstadoEventoEnum::PUBLICADO);
        $evento2->setRequiereVerificacionAcceso(false);
        $evento2->setCodigoVisual('COMIDA-TEST');
        $manager->persist($evento2);

        // Evento 3: Merienda (próximo, para probar adulto + ambos)
        $evento3 = new Evento();
        $evento3->setEntidad($entidad);
        $evento3->setTitulo('Merienda de Germanor');
        $evento3->setSlug('merienda-germanor-fixtures');
        $evento3->setDescripcion('Evento de prueba en franja MERIENDA para validar menús por tipo de persona.');
        $evento3->setTipoEvento(TipoEventoEnum::MERIENDA);
        $evento3->setFechaEvento($fechaEventoMerienda);
        $evento3->setHoraInicio(new \DateTimeImmutable('17:30'));
        $evento3->setHoraFin(new \DateTimeImmutable('20:30'));
        $evento3->setLugar('Terraza cubierta del casal');
        $evento3->setAforo(120);
        $evento3->setFechaInicioInscripcion($fechaEventoMerienda->modify('-10 days')->setTime(9, 0));
        $evento3->setFechaFinInscripcion($fechaEventoMerienda->modify('-1 day')->setTime(22, 0));
        $evento3->setVisible(true);
        $evento3->setAdmitePago(true);
        $evento3->setPermiteInvitados(false);
        $evento3->setEstado(EstadoEventoEnum::PUBLICADO);
        $evento3->setRequiereVerificacionAcceso(false);
        $evento3->setCodigoVisual('MERIENDA-TEST');
        $manager->persist($evento3);

        // Evento 4: Verbena (borrador)
        $evento4 = new Evento();
        $evento4->setEntidad($entidad);
        $evento4->setTitulo('Verbena de Verano (Borrador)');
        $evento4->setSlug('verbena-verano-borrador-fixtures');
        $evento4->setDescripcion('Evento en borrador para validar estados no publicados.');
        $evento4->setTipoEvento(TipoEventoEnum::OTRO);
        $evento4->setFechaEvento($fechaEventoBorrador);
        $evento4->setHoraInicio(new \DateTimeImmutable('22:00'));
        $evento4->setHoraFin(new \DateTimeImmutable('03:00'));
        $evento4->setLugar('C/ Joan Lloren');
        $evento4->setAforo(200);
        $evento4->setFechaInicioInscripcion($fechaEventoBorrador->modify('-20 days')->setTime(9, 0));
        $evento4->setFechaFinInscripcion($fechaEventoBorrador->modify('-2 days')->setTime(23, 0));
        $evento4->setVisible(false);
        $evento4->setAdmitePago(true);
        $evento4->setPermiteInvitados(false);
        $evento4->setEstado(EstadoEventoEnum::BORRADOR);
        $evento4->setRequiereVerificacionAcceso(false);
        $evento4->setCodigoVisual('BORRADOR-TEST');
        $manager->persist($evento4);

        // Evento 5: Demo de franjas y compatibilidad de menú (cercano)
        $eventoDemoFranjas = new Evento();
        $eventoDemoFranjas->setEntidad($entidad);
        $eventoDemoFranjas->setTitulo('Demo Franjas y Compatibilidad Actividades');
        $eventoDemoFranjas->setSlug('demo-franjas-compatibilidad-actividades-fixtures');
        $eventoDemoFranjas->setDescripcion('Evento de prueba cercano para validar detalle, selección de participantes y compatibilidades de menú.');
        $eventoDemoFranjas->setTipoEvento(TipoEventoEnum::COMIDA);
        $eventoDemoFranjas->setFechaEvento($fechaEventoDemo);
        $eventoDemoFranjas->setHoraInicio(new \DateTimeImmutable('10:30'));
        $eventoDemoFranjas->setHoraFin(new \DateTimeImmutable('22:30'));
        $eventoDemoFranjas->setLugar('Casal - Zona Demo Actividades');
        $eventoDemoFranjas->setAforo(120);
        $eventoDemoFranjas->setFechaInicioInscripcion($fechaEventoDemo->modify('-15 days')->setTime(8, 0));
        $eventoDemoFranjas->setFechaFinInscripcion($fechaEventoDemo->modify('-1 day')->setTime(23, 30));
        $eventoDemoFranjas->setVisible(true);
        $eventoDemoFranjas->setAdmitePago(true);
        $eventoDemoFranjas->setPermiteInvitados(true);
        $eventoDemoFranjas->setEstado(EstadoEventoEnum::PUBLICADO);
        $eventoDemoFranjas->setRequiereVerificacionAcceso(false);
        $eventoDemoFranjas->setCodigoVisual('DEMO-ACTIVIDAD');
        $manager->persist($eventoDemoFranjas);

        // Evento 6: publicado y sin comidas (sin invitados)
        $eventoSinComida = new Evento();
        $eventoSinComida->setEntidad($entidad);
        $eventoSinComida->setTitulo('Asamblea General (sin comidas)');
        $eventoSinComida->setSlug('asamblea-general-sin-comidas-fixtures');
        $eventoSinComida->setDescripcion('Evento publicado para probar el flujo sin menús ni invitados.');
        $eventoSinComida->setTipoEvento(TipoEventoEnum::OTRO);
        $eventoSinComida->setFechaEvento($fechaEventoSinComida);
        $eventoSinComida->setHoraInicio(new \DateTimeImmutable('19:00'));
        $eventoSinComida->setHoraFin(new \DateTimeImmutable('21:00'));
        $eventoSinComida->setLugar('Salón de actos');
        $eventoSinComida->setAforo(120);
        $eventoSinComida->setFechaInicioInscripcion($fechaEventoSinComida->modify('-7 days')->setTime(9, 0));
        $eventoSinComida->setFechaFinInscripcion($fechaEventoSinComida->modify('-1 day')->setTime(23, 0));
        $eventoSinComida->setVisible(true);
        $eventoSinComida->setAdmitePago(false);
        $eventoSinComida->setPermiteInvitados(false);
        $eventoSinComida->setEstado(EstadoEventoEnum::PUBLICADO);
        $eventoSinComida->setRequiereVerificacionAcceso(false);
        $eventoSinComida->setCodigoVisual('SIN-COMIDA');
        $manager->persist($eventoSinComida);

        // ============================================
        // 5. Crear Actividades para los eventos
        // ============================================

        // Actividades para Cena de Gala
        $actividadGala1 = new ActividadEvento();
        $actividadGala1->setEvento($evento1);
        $actividadGala1->setNombre('Actividad Cena Adulto');
        $actividadGala1->setDescripcion('Plato principal y postre para participantes adultos.');
        $actividadGala1->setTipoActividad(TipoActividadEnum::ADULTO);
        $actividadGala1->setFranjaComida(FranjaComidaEnum::CENA);
        $actividadGala1->setCompatibilidadPersona(CompatibilidadPersonaActividadEnum::ADULTO);
        $actividadGala1->setEsDePago(true);
        $actividadGala1->setPrecioBase(35.00);
        $actividadGala1->setPrecioAdultoInterno(25.00);
        $actividadGala1->setPrecioAdultoExterno(40.00);
        $actividadGala1->setPrecioInfantil(15.00);
        $actividadGala1->setUnidadesMaximas(70);
        $actividadGala1->setOrdenVisualizacion(1);
        $actividadGala1->setConfirmacionAutomatica(false);
        $actividadGala1->setActivo(true);
        $manager->persist($actividadGala1);

        $actividadGala2 = new ActividadEvento();
        $actividadGala2->setEvento($evento1);
        $actividadGala2->setNombre('Pack Refrescos y Pan');
        $actividadGala2->setDescripcion('Complemento libre compatible con adultos e infantiles.');
        $actividadGala2->setTipoActividad(TipoActividadEnum::LIBRE);
        $actividadGala2->setFranjaComida(FranjaComidaEnum::CENA);
        $actividadGala2->setCompatibilidadPersona(CompatibilidadPersonaActividadEnum::AMBOS);
        $actividadGala2->setEsDePago(true);
        $actividadGala2->setPrecioBase(15.00);
        $actividadGala2->setPrecioAdultoInterno(10.00);
        $actividadGala2->setPrecioAdultoExterno(15.00);
        $actividadGala2->setUnidadesMaximas(100);
        $actividadGala2->setOrdenVisualizacion(2);
        $actividadGala2->setConfirmacionAutomatica(true);
        $actividadGala2->setActivo(true);
        $manager->persist($actividadGala2);

        // Actividades para Comida Familiar (incluye opción infantil)
        $actividadPaella1 = new ActividadEvento();
        $actividadPaella1->setEvento($evento2);
        $actividadPaella1->setNombre('Comida Familiar General');
        $actividadPaella1->setDescripcion('Actividad libre para cualquier participante.');
        $actividadPaella1->setTipoActividad(TipoActividadEnum::LIBRE);
        $actividadPaella1->setFranjaComida(FranjaComidaEnum::COMIDA);
        $actividadPaella1->setCompatibilidadPersona(CompatibilidadPersonaActividadEnum::AMBOS);
        $actividadPaella1->setEsDePago(true);
        $actividadPaella1->setPrecioBase(13.00);
        $actividadPaella1->setPrecioAdultoInterno(11.00);
        $actividadPaella1->setPrecioAdultoExterno(14.50);
        $actividadPaella1->setPrecioInfantil(7.50);
        $actividadPaella1->setUnidadesMaximas(140);
        $actividadPaella1->setOrdenVisualizacion(1);
        $actividadPaella1->setConfirmacionAutomatica(true);
        $actividadPaella1->setActivo(true);
        $manager->persist($actividadPaella1);

        $actividadPaella2 = new ActividadEvento();
        $actividadPaella2->setEvento($evento2);
        $actividadPaella2->setNombre('Actividad Infantil Comida');
        $actividadPaella2->setDescripcion('Actividad específico para niños (compatibilidad infantil).');
        $actividadPaella2->setTipoActividad(TipoActividadEnum::INFANTIL);
        $actividadPaella2->setFranjaComida(FranjaComidaEnum::COMIDA);
        $actividadPaella2->setCompatibilidadPersona(CompatibilidadPersonaActividadEnum::INFANTIL);
        $actividadPaella2->setEsDePago(true);
        $actividadPaella2->setPrecioBase(8.50);
        $actividadPaella2->setPrecioAdultoInterno(8.50);
        $actividadPaella2->setPrecioAdultoExterno(8.50);
        $actividadPaella2->setPrecioInfantil(6.00);
        $actividadPaella2->setUnidadesMaximas(70);
        $actividadPaella2->setOrdenVisualizacion(2);
        $actividadPaella2->setConfirmacionAutomatica(true);
        $actividadPaella2->setActivo(true);
        $manager->persist($actividadPaella2);

        // Actividades para Merienda (adulto + ambos)
        $actividadVerbena = new ActividadEvento();
        $actividadVerbena->setEvento($evento3);
        $actividadVerbena->setNombre('Merienda Adulto Premium');
        $actividadVerbena->setDescripcion('Tabla de ibéricos y bebida para adultos.');
        $actividadVerbena->setTipoActividad(TipoActividadEnum::ADULTO);
        $actividadVerbena->setFranjaComida(FranjaComidaEnum::MERIENDA);
        $actividadVerbena->setCompatibilidadPersona(CompatibilidadPersonaActividadEnum::ADULTO);
        $actividadVerbena->setEsDePago(true);
        $actividadVerbena->setPrecioBase(11.00);
        $actividadVerbena->setPrecioAdultoInterno(9.00);
        $actividadVerbena->setPrecioAdultoExterno(12.00);
        $actividadVerbena->setUnidadesMaximas(85);
        $actividadVerbena->setOrdenVisualizacion(1);
        $actividadVerbena->setConfirmacionAutomatica(true);
        $actividadVerbena->setActivo(true);
        $manager->persist($actividadVerbena);

        $actividadMeriendaAmbos = new ActividadEvento();
        $actividadMeriendaAmbos->setEvento($evento3);
        $actividadMeriendaAmbos->setNombre('Merienda Clásica');
        $actividadMeriendaAmbos->setDescripcion('Bocadillo y refresco compatible con adultos e infantiles.');
        $actividadMeriendaAmbos->setTipoActividad(TipoActividadEnum::LIBRE);
        $actividadMeriendaAmbos->setFranjaComida(FranjaComidaEnum::MERIENDA);
        $actividadMeriendaAmbos->setCompatibilidadPersona(CompatibilidadPersonaActividadEnum::AMBOS);
        $actividadMeriendaAmbos->setEsDePago(true);
        $actividadMeriendaAmbos->setPrecioBase(6.50);
        $actividadMeriendaAmbos->setPrecioAdultoInterno(5.50);
        $actividadMeriendaAmbos->setPrecioAdultoExterno(7.00);
        $actividadMeriendaAmbos->setPrecioInfantil(4.50);
        $actividadMeriendaAmbos->setUnidadesMaximas(120);
        $actividadMeriendaAmbos->setOrdenVisualizacion(2);
        $actividadMeriendaAmbos->setConfirmacionAutomatica(true);
        $actividadMeriendaAmbos->setActivo(true);
        $manager->persist($actividadMeriendaAmbos);

        // Actividades demo (cobertura explícita de todas las franjas y compatibilidades)
        $actividadDemoAlmuerzoAdulto = new ActividadEvento();
        $actividadDemoAlmuerzoAdulto->setEvento($eventoDemoFranjas);
        $actividadDemoAlmuerzoAdulto->setNombre('[DEMO] Almuerzo Adulto');
        $actividadDemoAlmuerzoAdulto->setDescripcion('Demo franja ALMUERZO con compatibilidad ADULTO.');
        $actividadDemoAlmuerzoAdulto->setTipoActividad(TipoActividadEnum::ADULTO);
        $actividadDemoAlmuerzoAdulto->setFranjaComida(FranjaComidaEnum::ALMUERZO);
        $actividadDemoAlmuerzoAdulto->setCompatibilidadPersona(CompatibilidadPersonaActividadEnum::ADULTO);
        $actividadDemoAlmuerzoAdulto->setEsDePago(true);
        $actividadDemoAlmuerzoAdulto->setPrecioBase(8.00);
        $actividadDemoAlmuerzoAdulto->setPrecioAdultoInterno(6.50);
        $actividadDemoAlmuerzoAdulto->setPrecioAdultoExterno(9.00);
        $actividadDemoAlmuerzoAdulto->setPrecioInfantil(5.00);
        $actividadDemoAlmuerzoAdulto->setUnidadesMaximas(60);
        $actividadDemoAlmuerzoAdulto->setOrdenVisualizacion(1);
        $actividadDemoAlmuerzoAdulto->setConfirmacionAutomatica(true);
        $actividadDemoAlmuerzoAdulto->setActivo(true);
        $manager->persist($actividadDemoAlmuerzoAdulto);

        $actividadDemoComidaInfantil = new ActividadEvento();
        $actividadDemoComidaInfantil->setEvento($eventoDemoFranjas);
        $actividadDemoComidaInfantil->setNombre('[DEMO] Comida Infantil');
        $actividadDemoComidaInfantil->setDescripcion('Demo franja COMIDA con compatibilidad INFANTIL.');
        $actividadDemoComidaInfantil->setTipoActividad(TipoActividadEnum::INFANTIL);
        $actividadDemoComidaInfantil->setFranjaComida(FranjaComidaEnum::COMIDA);
        $actividadDemoComidaInfantil->setCompatibilidadPersona(CompatibilidadPersonaActividadEnum::INFANTIL);
        $actividadDemoComidaInfantil->setEsDePago(true);
        $actividadDemoComidaInfantil->setPrecioBase(11.00);
        $actividadDemoComidaInfantil->setPrecioAdultoInterno(11.00);
        $actividadDemoComidaInfantil->setPrecioAdultoExterno(11.00);
        $actividadDemoComidaInfantil->setPrecioInfantil(7.00);
        $actividadDemoComidaInfantil->setUnidadesMaximas(50);
        $actividadDemoComidaInfantil->setOrdenVisualizacion(2);
        $actividadDemoComidaInfantil->setConfirmacionAutomatica(true);
        $actividadDemoComidaInfantil->setActivo(true);
        $manager->persist($actividadDemoComidaInfantil);

        $actividadDemoMeriendaAmbos = new ActividadEvento();
        $actividadDemoMeriendaAmbos->setEvento($eventoDemoFranjas);
        $actividadDemoMeriendaAmbos->setNombre('[DEMO] Merienda Ambos');
        $actividadDemoMeriendaAmbos->setDescripcion('Demo franja MERIENDA con compatibilidad AMBOS.');
        $actividadDemoMeriendaAmbos->setTipoActividad(TipoActividadEnum::LIBRE);
        $actividadDemoMeriendaAmbos->setFranjaComida(FranjaComidaEnum::MERIENDA);
        $actividadDemoMeriendaAmbos->setCompatibilidadPersona(CompatibilidadPersonaActividadEnum::AMBOS);
        $actividadDemoMeriendaAmbos->setEsDePago(false);
        $actividadDemoMeriendaAmbos->setPrecioBase(0.00);
        $actividadDemoMeriendaAmbos->setPrecioAdultoInterno(0.00);
        $actividadDemoMeriendaAmbos->setPrecioAdultoExterno(0.00);
        $actividadDemoMeriendaAmbos->setPrecioInfantil(0.00);
        $actividadDemoMeriendaAmbos->setUnidadesMaximas(120);
        $actividadDemoMeriendaAmbos->setOrdenVisualizacion(3);
        $actividadDemoMeriendaAmbos->setConfirmacionAutomatica(true);
        $actividadDemoMeriendaAmbos->setActivo(true);
        $manager->persist($actividadDemoMeriendaAmbos);

        $actividadDemoCenaAdulto = new ActividadEvento();
        $actividadDemoCenaAdulto->setEvento($eventoDemoFranjas);
        $actividadDemoCenaAdulto->setNombre('[DEMO] Cena Adulto');
        $actividadDemoCenaAdulto->setDescripcion('Demo franja CENA con compatibilidad ADULTO.');
        $actividadDemoCenaAdulto->setTipoActividad(TipoActividadEnum::ADULTO);
        $actividadDemoCenaAdulto->setFranjaComida(FranjaComidaEnum::CENA);
        $actividadDemoCenaAdulto->setCompatibilidadPersona(CompatibilidadPersonaActividadEnum::ADULTO);
        $actividadDemoCenaAdulto->setEsDePago(true);
        $actividadDemoCenaAdulto->setPrecioBase(16.00);
        $actividadDemoCenaAdulto->setPrecioAdultoInterno(13.00);
        $actividadDemoCenaAdulto->setPrecioAdultoExterno(18.00);
        $actividadDemoCenaAdulto->setPrecioInfantil(10.00);
        $actividadDemoCenaAdulto->setUnidadesMaximas(80);
        $actividadDemoCenaAdulto->setOrdenVisualizacion(4);
        $actividadDemoCenaAdulto->setConfirmacionAutomatica(true);
        $actividadDemoCenaAdulto->setActivo(true);
        $manager->persist($actividadDemoCenaAdulto);

        // ============================================
        // 6. Crear invitados + selección de participantes + inscripciones demo
        // ============================================

        $invitado1 = new Invitado();
        $invitado1->setCreadoPor($usuario1);
        $invitado1->setEvento($evento1);
        $invitado1->setNombre('Laura');
        $invitado1->setApellidos('Giménez Torres');
        $invitado1->setTipoPersona(TipoPersonaEnum::ADULTO);
        $invitado1->setObservaciones('Invitada de prueba para flujo mixto usuario + invitado');
        $manager->persist($invitado1);

        $invitado2 = new Invitado();
        $invitado2->setCreadoPor($usuario1);
        $invitado2->setEvento($evento2);
        $invitado2->setNombre('Nora');
        $invitado2->setApellidos('Soler Peña');
        $invitado2->setTipoPersona(TipoPersonaEnum::INFANTIL);
        $invitado2->setObservaciones('Invitada infantil para validar compatibilidad de actividad INFANTIL.');
        $manager->persist($invitado2);

        $seleccionEvento2Adulto = new SeleccionParticipanteEvento();
        $seleccionEvento2Adulto->setEvento($evento2);
        $seleccionEvento2Adulto->setInscritoPorUsuario($usuario1);
        $seleccionEvento2Adulto->setUsuario($usuario1);

        $seleccionEvento2AdultoLinea = new SeleccionParticipanteEventoLinea();
        $seleccionEvento2AdultoLinea->setSeleccionParticipanteEvento($seleccionEvento2Adulto);
        $seleccionEvento2AdultoLinea->setEvento($evento2);
        $seleccionEvento2AdultoLinea->setUsuario($usuario1);
        $seleccionEvento2AdultoLinea->setActividad($actividadPaella1);
        $seleccionEvento2Adulto->addLinea($seleccionEvento2AdultoLinea);
        $manager->persist($seleccionEvento2Adulto);

        $seleccionEvento2Infantil = new SeleccionParticipanteEvento();
        $seleccionEvento2Infantil->setEvento($evento2);
        $seleccionEvento2Infantil->setInscritoPorUsuario($usuario1);
        $seleccionEvento2Infantil->setInvitado($invitado2);

        $seleccionEvento2InfantilLinea = new SeleccionParticipanteEventoLinea();
        $seleccionEvento2InfantilLinea->setSeleccionParticipanteEvento($seleccionEvento2Infantil);
        $seleccionEvento2InfantilLinea->setEvento($evento2);
        $seleccionEvento2InfantilLinea->setInvitado($invitado2);
        $seleccionEvento2InfantilLinea->setActividad($actividadPaella2);
        $seleccionEvento2Infantil->addLinea($seleccionEvento2InfantilLinea);
        $manager->persist($seleccionEvento2Infantil);

        $inscripcion1 = new Inscripcion();
        $inscripcion1->setCodigo('INSC-DEMO-001');
        $inscripcion1->setEntidad($entidad);
        $inscripcion1->setEvento($evento1);
        $inscripcion1->setUsuario($usuario1);
        $inscripcion1->setEstadoInscripcion(EstadoInscripcionEnum::CONFIRMADA);
        $inscripcion1->setMetodoPago(MetodoPagoEnum::BIZUM);
        $inscripcion1->setReferenciaPago('BIZUM-DEMO-001');
        $inscripcion1->setFechaPago($fechaEventoCena->modify('-1 days')->setTime(12, 0));

        $lineaUsuario = new InscripcionLinea();
        $lineaUsuario->setInscripcion($inscripcion1);
        $lineaUsuario->setUsuario($usuario1);
        $lineaUsuario->setActividad($actividadGala1);
        $lineaUsuario->setPrecioUnitario(25.00);
        $lineaUsuario->setEstadoLinea(EstadoLineaInscripcionEnum::CONFIRMADA);
        $lineaUsuario->crearSnapshot();
        $inscripcion1->addLinea($lineaUsuario);

        $lineaInvitado = new InscripcionLinea();
        $lineaInvitado->setInscripcion($inscripcion1);
        $lineaInvitado->setInvitado($invitado1);
        $lineaInvitado->setActividad($actividadGala2);
        $lineaInvitado->setPrecioUnitario(15.00);
        $lineaInvitado->setEstadoLinea(EstadoLineaInscripcionEnum::CONFIRMADA);
        $lineaInvitado->crearSnapshot();
        $inscripcion1->addLinea($lineaInvitado);

        $inscripcion1->setImporteTotal(40.00);
        $inscripcion1->setImportePagado(40.00);
        $inscripcion1->setEstadoPago(EstadoPagoEnum::PAGADO);
        $manager->persist($inscripcion1);

        $inscripcion2 = new Inscripcion();
        $inscripcion2->setCodigo('INSC-DEMO-002');
        $inscripcion2->setEntidad($entidad);
        $inscripcion2->setEvento($evento2);
        $inscripcion2->setUsuario($usuario1);
        $inscripcion2->setEstadoInscripcion(EstadoInscripcionEnum::CONFIRMADA);
        $inscripcion2->setMetodoPago(MetodoPagoEnum::EFECTIVO);
        $inscripcion2->setReferenciaPago('EFECTIVO-DEMO-002');
        $inscripcion2->setFechaPago($fechaEventoComida->modify('-2 days')->setTime(19, 0));

        $lineaComidaAdulto = new InscripcionLinea();
        $lineaComidaAdulto->setInscripcion($inscripcion2);
        $lineaComidaAdulto->setUsuario($usuario1);
        $lineaComidaAdulto->setActividad($actividadPaella1);
        $lineaComidaAdulto->setPrecioUnitario(11.00);
        $lineaComidaAdulto->setEstadoLinea(EstadoLineaInscripcionEnum::CONFIRMADA);
        $lineaComidaAdulto->crearSnapshot();
        $inscripcion2->addLinea($lineaComidaAdulto);

        $lineaComidaInfantil = new InscripcionLinea();
        $lineaComidaInfantil->setInscripcion($inscripcion2);
        $lineaComidaInfantil->setInvitado($invitado2);
        $lineaComidaInfantil->setActividad($actividadPaella2);
        $lineaComidaInfantil->setPrecioUnitario(6.00);
        $lineaComidaInfantil->setEstadoLinea(EstadoLineaInscripcionEnum::CONFIRMADA);
        $lineaComidaInfantil->crearSnapshot();
        $inscripcion2->addLinea($lineaComidaInfantil);

        $inscripcion2->setImporteTotal(17.00);
        $inscripcion2->setImportePagado(17.00);
        $inscripcion2->setEstadoPago(EstadoPagoEnum::PAGADO);
        $manager->persist($inscripcion2);


        // Flush all
        $manager->flush();


        $this->addReference('entidad.demo', $entidad);
    }

    public function getDependencies(): array
    {
        return [TipoEntidadFixtures::class, CargoMasterFixtures::class];
    }
}
