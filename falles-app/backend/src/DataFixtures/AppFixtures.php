<?php

namespace App\DataFixtures;

use App\Entity\Entidad;
use App\Entity\Usuario;
use App\Entity\Evento;
use App\Entity\MenuEvento;
use App\Entity\PersonaFamiliar;
use App\Entity\CensoEntrada;
use App\Enum\TipoEntidadEnum;
use App\Enum\TipoRelacionEconomicaEnum;
use App\Enum\CensadoViaEnum;
use App\Enum\EstadoValidacionEnum;
use App\Enum\TipoEventoEnum;
use App\Enum\EstadoEventoEnum;
use App\Enum\FranjaComidaEnum;
use App\Enum\CompatibilidadPersonaMenuEnum;
use App\Enum\TipoMenuEnum;
use App\Enum\TipoPersonaEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
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
        $entidad->setTipoEntidad(TipoEntidadEnum::FALLA);
        $entidad->setTerminologiaSocio('faller/a');
        $entidad->setTerminologiaEvento('mascletà');
        $entidad->setEmailContacto('info@fallallobre.es');
        $entidad->setTelefono('963456789');
        $entidad->setDireccion('C/ Joan Lloren, 25 - 46009 Valencia');
        $entidad->setCodigoRegistro('FALLA2024');
        $entidad->setTemporadaActual('2024');
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
        $superadmin->setTipoUsuarioEconomico(TipoRelacionEconomicaEnum::INTERNO);
        $superadmin->setEstadoValidacion(EstadoValidacionEnum::VALIDADO);
        $superadmin->setEsCensadoInterno(true);
        $superadmin->setCensadoVia(CensadoViaEnum::MANUAL);
        $superadmin->setFechaAltaCenso(new \DateTimeImmutable('2023-01-15'));
        $superadmin->setFechaValidacion(new \DateTimeImmutable('2023-01-16'));
        $manager->persist($superadmin);

        // Admin de entidad
        $adminEntidad = new Usuario();
        $adminEntidad->setEntidad($entidad);
        $adminEntidad->setNombre('María');
        $adminEntidad->setApellidos('García Admin');
        $adminEntidad->setEmail('admin@fallallobre.es');
        $adminEntidad->setTelefono('600222333');
        $adminEntidad->setPassword($this->passwordHasher->hashPassword($adminEntidad, 'admin123'));
        $adminEntidad->setRoles(['ROLE_ADMIN_ENTIDAD']);
        $adminEntidad->setActivo(true);
        $adminEntidad->setTipoUsuarioEconomico(TipoRelacionEconomicaEnum::INTERNO);
        $adminEntidad->setEstadoValidacion(EstadoValidacionEnum::VALIDADO);
        $adminEntidad->setEsCensadoInterno(true);
        $adminEntidad->setCensadoVia(CensadoViaEnum::MANUAL);
        $adminEntidad->setFechaAltaCenso(new \DateTimeImmutable('2023-02-01'));
        $adminEntidad->setFechaValidacion(new \DateTimeImmutable('2023-02-02'));
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
        $usuario1->setTipoUsuarioEconomico(TipoRelacionEconomicaEnum::INTERNO);
        $usuario1->setEstadoValidacion(EstadoValidacionEnum::VALIDADO);
        $usuario1->setEsCensadoInterno(true);
        $usuario1->setCensadoVia(CensadoViaEnum::EXCEL);
        $usuario1->setFechaSolicitudAlta(new \DateTimeImmutable('2024-01-10'));
        $usuario1->setFechaAltaCenso(new \DateTimeImmutable('2024-01-12'));
        $usuario1->setFechaValidacion(new \DateTimeImmutable('2024-01-12'));
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
        $usuario2->setTipoUsuarioEconomico(TipoRelacionEconomicaEnum::EXTERNO);
        $usuario2->setEstadoValidacion(EstadoValidacionEnum::PENDIENTE_VALIDACION);
        $usuario2->setEsCensadoInterno(false);
        $usuario2->setCensadoVia(CensadoViaEnum::EXCEL);
        $usuario2->setFechaSolicitudAlta(new \DateTimeImmutable('2024-02-01'));
        $manager->persist($usuario2);

        // ============================================
        // 3. Crear Personas Familiares
        // ============================================
        
        // Familiar 1 de usuario1 (cónyuge)
        $familiar1 = new PersonaFamiliar();
        $familiar1->setUsuarioPrincipal($usuario1);
        $familiar1->setNombre('Elena');
        $familiar1->setApellidos('Pérez García');
        $familiar1->setParentesco('cónyuge');
        $familiar1->setTipoPersona(TipoPersonaEnum::ADULTO);
        $familiar1->setTipoRelacionEconomica(TipoRelacionEconomicaEnum::INTERNO);
        $familiar1->setEstadoValidacion(EstadoValidacionEnum::VALIDADO);
        $familiar1->setFechaNacimiento(new \DateTimeImmutable('1985-06-15'));
        $familiar1->setValidadoPor($adminEntidad);
        $familiar1->setFechaValidacion(new \DateTimeImmutable('2024-01-13'));
        $familiar1->setActiva(true);
        $manager->persist($familiar1);

        // Familiar 2 de usuario1 (hijo)
        $familiar2 = new PersonaFamiliar();
        $familiar2->setUsuarioPrincipal($usuario1);
        $familiar2->setNombre('Pablo');
        $familiar2->setApellidos('Pérez Pérez');
        $familiar2->setParentesco('hijo');
        $familiar2->setTipoPersona(TipoPersonaEnum::INFANTIL);
        $familiar2->setTipoRelacionEconomica(TipoRelacionEconomicaEnum::INTERNO);
        $familiar2->setEstadoValidacion(EstadoValidacionEnum::VALIDADO);
        $familiar2->setFechaNacimiento(new \DateTimeImmutable('2015-03-20'));
        $familiar2->setValidadoPor($adminEntidad);
        $familiar2->setFechaValidacion(new \DateTimeImmutable('2024-01-13'));
        $familiar2->setActiva(true);
        $manager->persist($familiar2);

        // Familiar 3 de usuario1 (hija)
        $familiar3 = new PersonaFamiliar();
        $familiar3->setUsuarioPrincipal($usuario1);
        $familiar3->setNombre('Lucía');
        $familiar3->setApellidos('Pérez Pérez');
        $familiar3->setParentesco('hija');
        $familiar3->setTipoPersona(TipoPersonaEnum::INFANTIL);
        $familiar3->setTipoRelacionEconomica(TipoRelacionEconomicaEnum::INTERNO);
        $familiar3->setEstadoValidacion(EstadoValidacionEnum::VALIDADO);
        $familiar3->setFechaNacimiento(new \DateTimeImmutable('2018-09-10'));
        $familiar3->setValidadoPor($adminEntidad);
        $familiar3->setFechaValidacion(new \DateTimeImmutable('2024-01-13'));
        $familiar3->setActiva(true);
        $manager->persist($familiar3);

        // ============================================
        // 4. Crear Eventos
        // ============================================
        
        // Evento 1: Cena de Gala (próximo, inscripciones abiertas)
        $evento1 = new Evento();
        $evento1->setEntidad($entidad);
        $evento1->setTitulo('Cena de Gala 2024');
        $evento1->setSlug('cena-gala-2024');
        $evento1->setDescripcion('Cena de gala anual de la Falla Llibre Joan Lloren 25. Menu degustación con animación.');
        $evento1->setTipoEvento(TipoEventoEnum::CENA);
        $evento1->setFechaEvento(new \DateTimeImmutable('2024-03-15'));
        $evento1->setHoraInicio(new \DateTimeImmutable('21:00'));
        $evento1->setHoraFin(new \DateTimeImmutable('23:30'));
        $evento1->setLugar('Salón de Actos CSOA La Gemma');
        $evento1->setAforo(80);
        $evento1->setFechaInicioInscripcion(new \DateTimeImmutable('2024-01-15'));
        $evento1->setFechaFinInscripcion(new \DateTimeImmutable('2024-03-10'));
        $evento1->setVisible(true);
        $evento1->setPublicado(true);
        $evento1->setAdmitePago(true);
        $evento1->setEstado(EstadoEventoEnum::PUBLICADO);
        $evento1->setRequiereVerificacionAcceso(false);
        $evento1->setCodigoVisual('GALA24');
        $manager->persist($evento1);

        // Evento 2: Comida de Fallas (ya pasado)
        $evento2 = new Evento();
        $evento2->setEntidad($entidad);
        $evento2->setTitulo('Comida de Fallas 2024');
        $evento2->setSlug('comida-fallas-2024');
        $evento2->setDescripcion('Comida colectiva durante las Fallas en la calle.');
        $evento2->setTipoEvento(TipoEventoEnum::COMIDA);
        $evento2->setFechaEvento(new \DateTimeImmutable('2024-03-16'));
        $evento2->setHoraInicio(new \DateTimeImmutable('14:00'));
        $evento2->setHoraFin(new \DateTimeImmutable('17:00'));
        $evento2->setLugar('Plaça del Barri de Russafa');
        $evento2->setAforo(500);
        $evento2->setFechaInicioInscripcion(new \DateTimeImmutable('2024-02-01'));
        $evento2->setFechaFinInscripcion(new \DateTimeImmutable('2024-03-15'));
        $evento2->setVisible(true);
        $evento2->setPublicado(true);
        $evento2->setAdmitePago(false);
        $evento2->setEstado(EstadoEventoEnum::FINALIZADO);
        $evento2->setRequiereVerificacionAcceso(false);
        $evento2->setCodigoVisual('COMIDA24');
        $manager->persist($evento2);

        // Evento 3: Paella Solidaria (futuro)
        $evento3 = new Evento();
        $evento3->setEntidad($entidad);
        $evento3->setTitulo('Paella Solidaria');
        $evento3->setSlug('paella-solidaria-2024');
        $evento3->setDescripcion('Paella gigante solidaria a beneficio de la asociación local.');
        $evento3->setTipoEvento(TipoEventoEnum::COMIDA);
        $evento3->setFechaEvento(new \DateTimeImmutable('2024-04-21'));
        $evento3->setHoraInicio(new \DateTimeImmutable('12:00'));
        $evento3->setHoraFin(new \DateTimeImmutable('16:00'));
        $evento3->setLugar('Patio de la Falla');
        $evento3->setAforo(150);
        $evento3->setFechaInicioInscripcion(new \DateTimeImmutable('2024-03-01'));
        $evento3->setFechaFinInscripcion(new \DateTimeImmutable('2024-04-15'));
        $evento3->setVisible(true);
        $evento3->setPublicado(true);
        $evento3->setAdmitePago(true);
        $evento3->setEstado(EstadoEventoEnum::PUBLICADO);
        $evento3->setRequiereVerificacionAcceso(false);
        $evento3->setCodigoVisual('PAELLA24');
        $manager->persist($evento3);

        // Evento 4: Verbena (borrador)
        $evento4 = new Evento();
        $evento4->setEntidad($entidad);
        $evento4->setTitulo('Verbena de San Juan');
        $evento4->setSlug('verbena-san-juan-2024');
        $evento4->setDescripcion('Verbena noche de San Juan con dj y bar.');
        $evento4->setTipoEvento(TipoEventoEnum::OTRO);
        $evento4->setFechaEvento(new \DateTimeImmutable('2024-06-23'));
        $evento4->setHoraInicio(new \DateTimeImmutable('22:00'));
        $evento4->setHoraFin(new \DateTimeImmutable('2024-06-24 03:00'));
        $evento4->setLugar('C/ Joan Lloren');
        $evento4->setAforo(200);
        $evento4->setFechaInicioInscripcion(new \DateTimeImmutable('2024-05-01'));
        $evento4->setFechaFinInscripcion(new \DateTimeImmutable('2024-06-20'));
        $evento4->setVisible(false);
        $evento4->setPublicado(false);
        $evento4->setAdmitePago(true);
        $evento4->setEstado(EstadoEventoEnum::BORRADOR);
        $evento4->setRequiereVerificacionAcceso(false);
        $evento4->setCodigoVisual('VERBENA24');
        $manager->persist($evento4);

        // Evento 5: Demo de franjas y compatibilidad de menú
        $eventoDemoFranjas = new Evento();
        $eventoDemoFranjas->setEntidad($entidad);
        $eventoDemoFranjas->setTitulo('Demo Franjas y Compatibilidad Menús');
        $eventoDemoFranjas->setSlug('demo-franjas-compatibilidad-menus');
        $eventoDemoFranjas->setDescripcion('Evento de demostración para visualizar todas las franjas de comida y compatibilidades de persona en menús.');
        $eventoDemoFranjas->setTipoEvento(TipoEventoEnum::COMIDA);
        $eventoDemoFranjas->setFechaEvento(new \DateTimeImmutable('2026-09-20'));
        $eventoDemoFranjas->setHoraInicio(new \DateTimeImmutable('10:30'));
        $eventoDemoFranjas->setHoraFin(new \DateTimeImmutable('22:30'));
        $eventoDemoFranjas->setLugar('Casal - Zona Demo Menús');
        $eventoDemoFranjas->setAforo(120);
        $eventoDemoFranjas->setFechaInicioInscripcion(new \DateTimeImmutable('2026-08-20'));
        $eventoDemoFranjas->setFechaFinInscripcion(new \DateTimeImmutable('2026-09-18'));
        $eventoDemoFranjas->setVisible(true);
        $eventoDemoFranjas->setPublicado(true);
        $eventoDemoFranjas->setAdmitePago(true);
        $eventoDemoFranjas->setEstado(EstadoEventoEnum::PUBLICADO);
        $eventoDemoFranjas->setRequiereVerificacionAcceso(false);
        $eventoDemoFranjas->setCodigoVisual('DEMO26');
        $manager->persist($eventoDemoFranjas);

        // ============================================
        // 5. Crear Menús para los eventos
        // ============================================
        
        // Menús para Cena de Gala
        $menuGala1 = new MenuEvento();
        $menuGala1->setEvento($evento1);
        $menuGala1->setNombre('Menu Adulto Interno');
        $menuGala1->setDescripcion('Menu degustación 5 pasos para socios internos');
        $menuGala1->setTipoMenu(TipoMenuEnum::ADULTO);
        $menuGala1->setFranjaComida(FranjaComidaEnum::CENA);
        $menuGala1->setCompatibilidadPersona(CompatibilidadPersonaMenuEnum::ADULTO);
        $menuGala1->setEsDePago(true);
        $menuGala1->setPrecioBase(35.00);
        $menuGala1->setPrecioAdultoInterno(25.00);
        $menuGala1->setPrecioAdultoExterno(40.00);
        $menuGala1->setPrecioInfantil(15.00);
        $menuGala1->setUnidadesMaximas(70);
        $menuGala1->setOrdenVisualizacion(1);
        $menuGala1->setConfirmacionAutomatica(false);
        $menuGala1->setActivo(true);
        $manager->persist($menuGala1);

        $menuGala2 = new MenuEvento();
        $menuGala2->setEvento($evento1);
        $menuGala2->setNombre('Bebidas Adultas');
        $menuGala2->setDescripcion('Copa de cava y bebidas durante la cena');
        $menuGala2->setTipoMenu(TipoMenuEnum::LIBRE);
        $menuGala2->setFranjaComida(FranjaComidaEnum::CENA);
        $menuGala2->setCompatibilidadPersona(CompatibilidadPersonaMenuEnum::AMBOS);
        $menuGala2->setEsDePago(true);
        $menuGala2->setPrecioBase(15.00);
        $menuGala2->setPrecioAdultoInterno(10.00);
        $menuGala2->setPrecioAdultoExterno(15.00);
        $menuGala2->setUnidadesMaximas(100);
        $menuGala2->setOrdenVisualizacion(2);
        $menuGala2->setConfirmacionAutomatica(true);
        $menuGala2->setActivo(true);
        $manager->persist($menuGala2);

        // Menús para Paella Solidaria
        $menuPaella1 = new MenuEvento();
        $menuPaella1->setEvento($evento3);
        $menuPaella1->setNombre('Paella + Postre');
        $menuPaella1->setDescripcion('Ración de paella gigante + postre casero');
        $menuPaella1->setTipoMenu(TipoMenuEnum::LIBRE);
        $menuPaella1->setFranjaComida(FranjaComidaEnum::COMIDA);
        $menuPaella1->setCompatibilidadPersona(CompatibilidadPersonaMenuEnum::AMBOS);
        $menuPaella1->setEsDePago(true);
        $menuPaella1->setPrecioBase(12.00);
        $menuPaella1->setPrecioAdultoInterno(10.00);
        $menuPaella1->setPrecioAdultoExterno(14.00);
        $menuPaella1->setPrecioInfantil(6.00);
        $menuPaella1->setUnidadesMaximas(140);
        $menuPaella1->setOrdenVisualizacion(1);
        $menuPaella1->setConfirmacionAutomatica(true);
        $menuPaella1->setActivo(true);
        $manager->persist($menuPaella1);

        $menuPaella2 = new MenuEvento();
        $menuPaella2->setEvento($evento3);
        $menuPaella2->setNombre('Barra Libre');
        $menuPaella2->setDescripcion('Bebidas durante 4 horas');
        $menuPaella2->setTipoMenu(TipoMenuEnum::LIBRE);
        $menuPaella2->setFranjaComida(FranjaComidaEnum::COMIDA);
        $menuPaella2->setCompatibilidadPersona(CompatibilidadPersonaMenuEnum::ADULTO);
        $menuPaella2->setEsDePago(true);
        $menuPaella2->setPrecioBase(20.00);
        $menuPaella2->setPrecioAdultoInterno(15.00);
        $menuPaella2->setPrecioAdultoExterno(20.00);
        $menuPaella2->setUnidadesMaximas(100);
        $menuPaella2->setOrdenVisualizacion(2);
        $menuPaella2->setConfirmacionAutomatica(true);
        $menuPaella2->setActivo(true);
        $manager->persist($menuPaella2);

        // Menú para Verbena (gratis)
        $menuVerbena = new MenuEvento();
        $menuVerbena->setEvento($evento4);
        $menuVerbena->setNombre('Entrada Verbena');
        $menuVerbena->setDescripcion('Acceso a la verbena');
        $menuVerbena->setTipoMenu(TipoMenuEnum::LIBRE);
        $menuVerbena->setFranjaComida(FranjaComidaEnum::CENA);
        $menuVerbena->setCompatibilidadPersona(CompatibilidadPersonaMenuEnum::AMBOS);
        $menuVerbena->setEsDePago(true);
        $menuVerbena->setPrecioBase(0.00);
        $menuVerbena->setPrecioAdultoInterno(0.00);
        $menuVerbena->setPrecioAdultoExterno(5.00);
        $menuVerbena->setUnidadesMaximas(200);
        $menuVerbena->setOrdenVisualizacion(1);
        $menuVerbena->setConfirmacionAutomatica(true);
        $menuVerbena->setActivo(true);
        $manager->persist($menuVerbena);

        // Menús demo (cobertura explícita de todas las franjas y compatibilidades)
        $menuDemoAlmuerzoAdulto = new MenuEvento();
        $menuDemoAlmuerzoAdulto->setEvento($eventoDemoFranjas);
        $menuDemoAlmuerzoAdulto->setNombre('[DEMO] Almuerzo Adulto');
        $menuDemoAlmuerzoAdulto->setDescripcion('Demo franja ALMUERZO con compatibilidad ADULTO.');
        $menuDemoAlmuerzoAdulto->setTipoMenu(TipoMenuEnum::ADULTO);
        $menuDemoAlmuerzoAdulto->setFranjaComida(FranjaComidaEnum::ALMUERZO);
        $menuDemoAlmuerzoAdulto->setCompatibilidadPersona(CompatibilidadPersonaMenuEnum::ADULTO);
        $menuDemoAlmuerzoAdulto->setEsDePago(true);
        $menuDemoAlmuerzoAdulto->setPrecioBase(8.00);
        $menuDemoAlmuerzoAdulto->setPrecioAdultoInterno(6.50);
        $menuDemoAlmuerzoAdulto->setPrecioAdultoExterno(9.00);
        $menuDemoAlmuerzoAdulto->setPrecioInfantil(5.00);
        $menuDemoAlmuerzoAdulto->setUnidadesMaximas(60);
        $menuDemoAlmuerzoAdulto->setOrdenVisualizacion(1);
        $menuDemoAlmuerzoAdulto->setConfirmacionAutomatica(true);
        $menuDemoAlmuerzoAdulto->setActivo(true);
        $manager->persist($menuDemoAlmuerzoAdulto);

        $menuDemoComidaInfantil = new MenuEvento();
        $menuDemoComidaInfantil->setEvento($eventoDemoFranjas);
        $menuDemoComidaInfantil->setNombre('[DEMO] Comida Infantil');
        $menuDemoComidaInfantil->setDescripcion('Demo franja COMIDA con compatibilidad INFANTIL.');
        $menuDemoComidaInfantil->setTipoMenu(TipoMenuEnum::INFANTIL);
        $menuDemoComidaInfantil->setFranjaComida(FranjaComidaEnum::COMIDA);
        $menuDemoComidaInfantil->setCompatibilidadPersona(CompatibilidadPersonaMenuEnum::INFANTIL);
        $menuDemoComidaInfantil->setEsDePago(true);
        $menuDemoComidaInfantil->setPrecioBase(11.00);
        $menuDemoComidaInfantil->setPrecioAdultoInterno(11.00);
        $menuDemoComidaInfantil->setPrecioAdultoExterno(11.00);
        $menuDemoComidaInfantil->setPrecioInfantil(7.00);
        $menuDemoComidaInfantil->setUnidadesMaximas(50);
        $menuDemoComidaInfantil->setOrdenVisualizacion(2);
        $menuDemoComidaInfantil->setConfirmacionAutomatica(true);
        $menuDemoComidaInfantil->setActivo(true);
        $manager->persist($menuDemoComidaInfantil);

        $menuDemoMeriendaAmbos = new MenuEvento();
        $menuDemoMeriendaAmbos->setEvento($eventoDemoFranjas);
        $menuDemoMeriendaAmbos->setNombre('[DEMO] Merienda Ambos');
        $menuDemoMeriendaAmbos->setDescripcion('Demo franja MERIENDA con compatibilidad AMBOS.');
        $menuDemoMeriendaAmbos->setTipoMenu(TipoMenuEnum::LIBRE);
        $menuDemoMeriendaAmbos->setFranjaComida(FranjaComidaEnum::MERIENDA);
        $menuDemoMeriendaAmbos->setCompatibilidadPersona(CompatibilidadPersonaMenuEnum::AMBOS);
        $menuDemoMeriendaAmbos->setEsDePago(false);
        $menuDemoMeriendaAmbos->setPrecioBase(0.00);
        $menuDemoMeriendaAmbos->setPrecioAdultoInterno(0.00);
        $menuDemoMeriendaAmbos->setPrecioAdultoExterno(0.00);
        $menuDemoMeriendaAmbos->setPrecioInfantil(0.00);
        $menuDemoMeriendaAmbos->setUnidadesMaximas(120);
        $menuDemoMeriendaAmbos->setOrdenVisualizacion(3);
        $menuDemoMeriendaAmbos->setConfirmacionAutomatica(true);
        $menuDemoMeriendaAmbos->setActivo(true);
        $manager->persist($menuDemoMeriendaAmbos);

        $menuDemoCenaAdulto = new MenuEvento();
        $menuDemoCenaAdulto->setEvento($eventoDemoFranjas);
        $menuDemoCenaAdulto->setNombre('[DEMO] Cena Adulto');
        $menuDemoCenaAdulto->setDescripcion('Demo franja CENA con compatibilidad ADULTO.');
        $menuDemoCenaAdulto->setTipoMenu(TipoMenuEnum::ADULTO);
        $menuDemoCenaAdulto->setFranjaComida(FranjaComidaEnum::CENA);
        $menuDemoCenaAdulto->setCompatibilidadPersona(CompatibilidadPersonaMenuEnum::ADULTO);
        $menuDemoCenaAdulto->setEsDePago(true);
        $menuDemoCenaAdulto->setPrecioBase(16.00);
        $menuDemoCenaAdulto->setPrecioAdultoInterno(13.00);
        $menuDemoCenaAdulto->setPrecioAdultoExterno(18.00);
        $menuDemoCenaAdulto->setPrecioInfantil(10.00);
        $menuDemoCenaAdulto->setUnidadesMaximas(80);
        $menuDemoCenaAdulto->setOrdenVisualizacion(4);
        $menuDemoCenaAdulto->setConfirmacionAutomatica(true);
        $menuDemoCenaAdulto->setActivo(true);
        $manager->persist($menuDemoCenaAdulto);

        // ============================================
        // 6. Crear Census de entrada (importación masiva)
        // ============================================
        
        $censo1 = new CensoEntrada();
        $censo1->setEntidad($entidad);
        $censo1->setNombre('Pedro');
        $censo1->setApellidos('García Martínez');
        $censo1->setEmail('pedro.garcia@example.com');
        $censo1->setDni('12345678A');
        $censo1->setParentesco('vecino');
        $censo1->setTipoPersona(TipoPersonaEnum::ADULTO);
        $censo1->setTipoRelacionEconomica(TipoRelacionEconomicaEnum::EXTERNO);
        $censo1->setTemporada('2024');
        $censo1->setProcesado(false);
        $manager->persist($censo1);

        $censo2 = new CensoEntrada();
        $censo2->setEntidad($entidad);
        $censo2->setNombre('Lucía');
        $censo2->setApellidos('Fernández Ruiz');
        $censo2->setEmail('lucia.fernandez@example.com');
        $censo2->setDni('87654321B');
        $censo2->setParentesco('aficionado');
        $censo2->setTipoPersona(TipoPersonaEnum::ADULTO);
        $censo2->setTipoRelacionEconomica(TipoRelacionEconomicaEnum::EXTERNO);
        $censo2->setTemporada('2024');
        $censo2->setProcesado(false);
        $manager->persist($censo2);

        $censo3 = new CensoEntrada();
        $censo3->setEntidad($entidad);
        $censo3->setNombre('Miguel');
        $censo3->setApellidos('Hernández Sánchez');
        $censo3->setEmail('miguel.h@example.com');
        $censo3->setParentesco('faller');
        $censo3->setTipoPersona(TipoPersonaEnum::ADULTO);
        $censo3->setTipoRelacionEconomica(TipoRelacionEconomicaEnum::INTERNO);
        $censo3->setTemporada('2024');
        $censo3->setProcesado(true);
        $manager->persist($censo3);

        // Flush all
        $manager->flush();

        // Add admin to entidad
        $entidad->addAdmin($adminEntidad);
        $manager->flush();
    }
}
