<?php

namespace App\DataFixtures;

use App\Entity\Entidad;
use App\Entity\Usuario;
use App\Entity\Evento;
use App\Entity\MenuEvento;
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
use App\Enum\CompatibilidadPersonaMenuEnum;
use App\Enum\TipoMenuEnum;
use App\Enum\TipoPersonaEnum;
use App\Enum\EstadoInscripcionEnum;
use App\Enum\EstadoPagoEnum;
use App\Enum\EstadoLineaInscripcionEnum;
use App\Enum\MetodoPagoEnum;
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
        $relacionadoConyuge->setTipoUsuarioEconomico(TipoRelacionEconomicaEnum::INTERNO);
        $relacionadoConyuge->setEstadoValidacion(EstadoValidacionEnum::VALIDADO);
        $relacionadoConyuge->setEsCensadoInterno(true);
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
        $relacionadoHijo->setTipoUsuarioEconomico(TipoRelacionEconomicaEnum::INTERNO);
        $relacionadoHijo->setEstadoValidacion(EstadoValidacionEnum::VALIDADO);
        $relacionadoHijo->setEsCensadoInterno(true);
        $relacionadoHijo->setCensadoVia(CensadoViaEnum::MANUAL);
        $relacionadoHijo->setFechaNacimiento(new \DateTimeImmutable('2015-03-20'));
        $manager->persist($relacionadoHijo);

        $relacionConyuge = new RelacionUsuario();
        $relacionConyuge->setUsuarioOrigen($usuario1);
        $relacionConyuge->setUsuarioDestino($relacionadoConyuge);
        $relacionConyuge->setTipoRelacion(TipoRelacionEnum::CONYUGE);
        $manager->persist($relacionConyuge);

        $relacionHijo = new RelacionUsuario();
        $relacionHijo->setUsuarioOrigen($usuario1);
        $relacionHijo->setUsuarioDestino($relacionadoHijo);
        $relacionHijo->setTipoRelacion(TipoRelacionEnum::HIJO);
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
        $evento1->setPublicado(true);
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
        $evento2->setPublicado(true);
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
        $evento3->setPublicado(true);
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
        $evento4->setPublicado(false);
        $evento4->setAdmitePago(true);
        $evento4->setPermiteInvitados(false);
        $evento4->setEstado(EstadoEventoEnum::BORRADOR);
        $evento4->setRequiereVerificacionAcceso(false);
        $evento4->setCodigoVisual('BORRADOR-TEST');
        $manager->persist($evento4);

        // Evento 5: Demo de franjas y compatibilidad de menú (cercano)
        $eventoDemoFranjas = new Evento();
        $eventoDemoFranjas->setEntidad($entidad);
        $eventoDemoFranjas->setTitulo('Demo Franjas y Compatibilidad Menús');
        $eventoDemoFranjas->setSlug('demo-franjas-compatibilidad-menus-fixtures');
        $eventoDemoFranjas->setDescripcion('Evento de prueba cercano para validar detalle, selección de participantes y compatibilidades de menú.');
        $eventoDemoFranjas->setTipoEvento(TipoEventoEnum::COMIDA);
        $eventoDemoFranjas->setFechaEvento($fechaEventoDemo);
        $eventoDemoFranjas->setHoraInicio(new \DateTimeImmutable('10:30'));
        $eventoDemoFranjas->setHoraFin(new \DateTimeImmutable('22:30'));
        $eventoDemoFranjas->setLugar('Casal - Zona Demo Menús');
        $eventoDemoFranjas->setAforo(120);
        $eventoDemoFranjas->setFechaInicioInscripcion($fechaEventoDemo->modify('-15 days')->setTime(8, 0));
        $eventoDemoFranjas->setFechaFinInscripcion($fechaEventoDemo->modify('-1 day')->setTime(23, 30));
        $eventoDemoFranjas->setVisible(true);
        $eventoDemoFranjas->setPublicado(true);
        $eventoDemoFranjas->setAdmitePago(true);
        $eventoDemoFranjas->setPermiteInvitados(true);
        $eventoDemoFranjas->setEstado(EstadoEventoEnum::PUBLICADO);
        $eventoDemoFranjas->setRequiereVerificacionAcceso(false);
        $eventoDemoFranjas->setCodigoVisual('DEMO-MENU');
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
        $eventoSinComida->setPublicado(true);
        $eventoSinComida->setAdmitePago(false);
        $eventoSinComida->setPermiteInvitados(false);
        $eventoSinComida->setEstado(EstadoEventoEnum::PUBLICADO);
        $eventoSinComida->setRequiereVerificacionAcceso(false);
        $eventoSinComida->setCodigoVisual('SIN-COMIDA');
        $manager->persist($eventoSinComida);

        // ============================================
        // 5. Crear Menús para los eventos
        // ============================================

        // Menús para Cena de Gala
        $menuGala1 = new MenuEvento();
        $menuGala1->setEvento($evento1);
        $menuGala1->setNombre('Menú Cena Adulto');
        $menuGala1->setDescripcion('Plato principal y postre para participantes adultos.');
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
        $menuGala2->setNombre('Pack Refrescos y Pan');
        $menuGala2->setDescripcion('Complemento libre compatible con adultos e infantiles.');
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

        // Menús para Comida Familiar (incluye opción infantil)
        $menuPaella1 = new MenuEvento();
        $menuPaella1->setEvento($evento2);
        $menuPaella1->setNombre('Comida Familiar General');
        $menuPaella1->setDescripcion('Menú libre para cualquier participante.');
        $menuPaella1->setTipoMenu(TipoMenuEnum::LIBRE);
        $menuPaella1->setFranjaComida(FranjaComidaEnum::COMIDA);
        $menuPaella1->setCompatibilidadPersona(CompatibilidadPersonaMenuEnum::AMBOS);
        $menuPaella1->setEsDePago(true);
        $menuPaella1->setPrecioBase(13.00);
        $menuPaella1->setPrecioAdultoInterno(11.00);
        $menuPaella1->setPrecioAdultoExterno(14.50);
        $menuPaella1->setPrecioInfantil(7.50);
        $menuPaella1->setUnidadesMaximas(140);
        $menuPaella1->setOrdenVisualizacion(1);
        $menuPaella1->setConfirmacionAutomatica(true);
        $menuPaella1->setActivo(true);
        $manager->persist($menuPaella1);

        $menuPaella2 = new MenuEvento();
        $menuPaella2->setEvento($evento2);
        $menuPaella2->setNombre('Menú Infantil Comida');
        $menuPaella2->setDescripcion('Menú específico para niños (compatibilidad infantil).');
        $menuPaella2->setTipoMenu(TipoMenuEnum::INFANTIL);
        $menuPaella2->setFranjaComida(FranjaComidaEnum::COMIDA);
        $menuPaella2->setCompatibilidadPersona(CompatibilidadPersonaMenuEnum::INFANTIL);
        $menuPaella2->setEsDePago(true);
        $menuPaella2->setPrecioBase(8.50);
        $menuPaella2->setPrecioAdultoInterno(8.50);
        $menuPaella2->setPrecioAdultoExterno(8.50);
        $menuPaella2->setPrecioInfantil(6.00);
        $menuPaella2->setUnidadesMaximas(70);
        $menuPaella2->setOrdenVisualizacion(2);
        $menuPaella2->setConfirmacionAutomatica(true);
        $menuPaella2->setActivo(true);
        $manager->persist($menuPaella2);

        // Menús para Merienda (adulto + ambos)
        $menuVerbena = new MenuEvento();
        $menuVerbena->setEvento($evento3);
        $menuVerbena->setNombre('Merienda Adulto Premium');
        $menuVerbena->setDescripcion('Tabla de ibéricos y bebida para adultos.');
        $menuVerbena->setTipoMenu(TipoMenuEnum::ADULTO);
        $menuVerbena->setFranjaComida(FranjaComidaEnum::MERIENDA);
        $menuVerbena->setCompatibilidadPersona(CompatibilidadPersonaMenuEnum::ADULTO);
        $menuVerbena->setEsDePago(true);
        $menuVerbena->setPrecioBase(11.00);
        $menuVerbena->setPrecioAdultoInterno(9.00);
        $menuVerbena->setPrecioAdultoExterno(12.00);
        $menuVerbena->setUnidadesMaximas(85);
        $menuVerbena->setOrdenVisualizacion(1);
        $menuVerbena->setConfirmacionAutomatica(true);
        $menuVerbena->setActivo(true);
        $manager->persist($menuVerbena);

        $menuMeriendaAmbos = new MenuEvento();
        $menuMeriendaAmbos->setEvento($evento3);
        $menuMeriendaAmbos->setNombre('Merienda Clásica');
        $menuMeriendaAmbos->setDescripcion('Bocadillo y refresco compatible con adultos e infantiles.');
        $menuMeriendaAmbos->setTipoMenu(TipoMenuEnum::LIBRE);
        $menuMeriendaAmbos->setFranjaComida(FranjaComidaEnum::MERIENDA);
        $menuMeriendaAmbos->setCompatibilidadPersona(CompatibilidadPersonaMenuEnum::AMBOS);
        $menuMeriendaAmbos->setEsDePago(true);
        $menuMeriendaAmbos->setPrecioBase(6.50);
        $menuMeriendaAmbos->setPrecioAdultoInterno(5.50);
        $menuMeriendaAmbos->setPrecioAdultoExterno(7.00);
        $menuMeriendaAmbos->setPrecioInfantil(4.50);
        $menuMeriendaAmbos->setUnidadesMaximas(120);
        $menuMeriendaAmbos->setOrdenVisualizacion(2);
        $menuMeriendaAmbos->setConfirmacionAutomatica(true);
        $menuMeriendaAmbos->setActivo(true);
        $manager->persist($menuMeriendaAmbos);

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
        $invitado2->setObservaciones('Invitada infantil para validar compatibilidad de menú INFANTIL.');
        $manager->persist($invitado2);

        $seleccionEvento2Adulto = new SeleccionParticipanteEvento();
        $seleccionEvento2Adulto->setEvento($evento2);
        $seleccionEvento2Adulto->setInscritoPorUsuario($usuario1);
        $seleccionEvento2Adulto->setUsuario($usuario1);

        $seleccionEvento2AdultoLinea = new SeleccionParticipanteEventoLinea();
        $seleccionEvento2AdultoLinea->setSeleccionParticipanteEvento($seleccionEvento2Adulto);
        $seleccionEvento2AdultoLinea->setEvento($evento2);
        $seleccionEvento2AdultoLinea->setUsuario($usuario1);
        $seleccionEvento2AdultoLinea->setMenu($menuPaella1);
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
        $seleccionEvento2InfantilLinea->setMenu($menuPaella2);
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
        $lineaUsuario->setMenu($menuGala1);
        $lineaUsuario->setPrecioUnitario(25.00);
        $lineaUsuario->setEstadoLinea(EstadoLineaInscripcionEnum::CONFIRMADA);
        $lineaUsuario->crearSnapshot();
        $inscripcion1->addLinea($lineaUsuario);

        $lineaInvitado = new InscripcionLinea();
        $lineaInvitado->setInscripcion($inscripcion1);
        $lineaInvitado->setInvitado($invitado1);
        $lineaInvitado->setMenu($menuGala2);
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
        $lineaComidaAdulto->setMenu($menuPaella1);
        $lineaComidaAdulto->setPrecioUnitario(11.00);
        $lineaComidaAdulto->setEstadoLinea(EstadoLineaInscripcionEnum::CONFIRMADA);
        $lineaComidaAdulto->crearSnapshot();
        $inscripcion2->addLinea($lineaComidaAdulto);

        $lineaComidaInfantil = new InscripcionLinea();
        $lineaComidaInfantil->setInscripcion($inscripcion2);
        $lineaComidaInfantil->setInvitado($invitado2);
        $lineaComidaInfantil->setMenu($menuPaella2);
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

        // Add admin to entidad
        $entidad->addAdmin($adminEntidad);
        $manager->flush();
    }
}
