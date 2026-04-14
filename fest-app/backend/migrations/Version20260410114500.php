<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410114500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed demo data: entidad, admin, evento con/sin invitados, menus adulto/infantil y actividades para adulto/infantil/ambos.';
    }

    public function up(Schema $schema): void
    {
        $now = new \DateTimeImmutable();
        $nowSql = $now->format('Y-m-d H:i:s');

        $entidadSlug = 'demo-entidad-migracion';
        $entidadId = $this->findEntidadIdBySlug($entidadSlug);

        if ($entidadId === null) {
            $entidadId = $this->uuid();
            $this->addSql(
                'INSERT INTO entidad (
                    id, nombre, slug, descripcion, tipo_entidad, terminologia_socio, terminologia_evento,
                    logo, email_contacto, telefono, direccion, codigo_registro, temporada_actual,
                    activa, censado, created_at, updated_at
                ) VALUES (
                    :id, :nombre, :slug, :descripcion, :tipo_entidad, :terminologia_socio, :terminologia_evento,
                    NULL, :email_contacto, NULL, NULL, :codigo_registro, :temporada_actual,
                    1, 1, :created_at, :updated_at
                )',
                [
                    'id' => $entidadId,
                    'nombre' => 'Entidad Demo Migracion',
                    'slug' => $entidadSlug,
                    'descripcion' => 'Datos demo creados por migracion para pruebas de admin, menus, actividades e invitados.',
                    'tipo_entidad' => 'comparsa',
                    'terminologia_socio' => null,
                    'terminologia_evento' => null,
                    'email_contacto' => 'demo.entidad@festapp.local',
                    'codigo_registro' => 'DEMO-MIGR-2026',
                    'temporada_actual' => '2026',
                    'created_at' => $nowSql,
                    'updated_at' => $nowSql,
                ]
            );
        }

        $adminEmail = 'admin.demo@festapp.local';
        $adminId = $this->findUsuarioIdByEmail($adminEmail);

        if ($adminId === null) {
            $adminId = $this->uuid();
            $this->addSql(
                'INSERT INTO usuario (
                    id, nombre, apellidos, nombre_completo, email, telefono, password, roles,
                    activo, tipo_usuario_economico, estado_validacion, es_censado_interno,
                    codigo_registro_usado, censado_via, antiguedad, antiguedad_real, forma_pago_preferida,
                    debe_cambiar_password, password_actualizada_at, fecha_solicitud_alta, fecha_alta_censo,
                    fecha_baja_censo, motivo_baja_censo, fecha_validacion, created_at, updated_at,
                    fecha_nacimiento, entidad_id, validado_por_id
                ) VALUES (
                    :id, :nombre, :apellidos, :nombre_completo, :email, NULL, :password, :roles,
                    1, :tipo_usuario_economico, :estado_validacion, 1,
                    NULL, NULL, NULL, NULL, NULL,
                    0, :password_actualizada_at, NULL, NULL,
                    NULL, NULL, :fecha_validacion, :created_at, :updated_at,
                    NULL, :entidad_id, NULL
                )',
                [
                    'id' => $adminId,
                    'nombre' => 'Admin',
                    'apellidos' => 'Demo',
                    'nombre_completo' => 'Admin Demo',
                    'email' => $adminEmail,
                    'password' => password_hash('Admin1234!', PASSWORD_BCRYPT),
                    'roles' => json_encode(['ROLE_ADMIN_ENTIDAD', 'ROLE_USER'], JSON_THROW_ON_ERROR),
                    'tipo_usuario_economico' => 'interno',
                    'estado_validacion' => 'validado',
                    'password_actualizada_at' => $nowSql,
                    'fecha_validacion' => $nowSql,
                    'created_at' => $nowSql,
                    'updated_at' => $nowSql,
                    'entidad_id' => $entidadId,
                ]
            );
        }

        $socioEmail = 'socio.demo@festapp.local';
        $socioId = $this->findUsuarioIdByEmail($socioEmail);

        if ($socioId === null) {
            $socioId = $this->uuid();
            $this->addSql(
                'INSERT INTO usuario (
                    id, nombre, apellidos, nombre_completo, email, telefono, password, roles,
                    activo, tipo_usuario_economico, estado_validacion, es_censado_interno,
                    codigo_registro_usado, censado_via, antiguedad, antiguedad_real, forma_pago_preferida,
                    debe_cambiar_password, password_actualizada_at, fecha_solicitud_alta, fecha_alta_censo,
                    fecha_baja_censo, motivo_baja_censo, fecha_validacion, created_at, updated_at,
                    fecha_nacimiento, entidad_id, validado_por_id
                ) VALUES (
                    :id, :nombre, :apellidos, :nombre_completo, :email, NULL, :password, :roles,
                    1, :tipo_usuario_economico, :estado_validacion, 1,
                    NULL, NULL, NULL, NULL, NULL,
                    0, :password_actualizada_at, NULL, NULL,
                    NULL, NULL, :fecha_validacion, :created_at, :updated_at,
                    NULL, :entidad_id, :validado_por_id
                )',
                [
                    'id' => $socioId,
                    'nombre' => 'Socio',
                    'apellidos' => 'Demo',
                    'nombre_completo' => 'Socio Demo',
                    'email' => $socioEmail,
                    'password' => password_hash('User1234!', PASSWORD_BCRYPT),
                    'roles' => json_encode(['ROLE_USER'], JSON_THROW_ON_ERROR),
                    'tipo_usuario_economico' => 'interno',
                    'estado_validacion' => 'validado',
                    'password_actualizada_at' => $nowSql,
                    'fecha_validacion' => $nowSql,
                    'created_at' => $nowSql,
                    'updated_at' => $nowSql,
                    'entidad_id' => $entidadId,
                    'validado_por_id' => $adminId,
                ]
            );
        }

        $this->addSql(
            'INSERT INTO entidad_admins (entidad_id, usuario_id)
             SELECT :entidad_id, :usuario_id
             WHERE NOT EXISTS (
                 SELECT 1 FROM entidad_admins ea WHERE ea.entidad_id = :entidad_id AND ea.usuario_id = :usuario_id
             )',
            [
                'entidad_id' => $entidadId,
                'usuario_id' => $adminId,
            ]
        );

        $eventoConInvitadosId = $this->upsertEvento(
            entidadId: $entidadId,
            slug: 'demo-comida-con-invitados',
            titulo: 'Comida Demo con Invitados',
            descripcion: 'Evento demo con menus adulto/infantil y actividades para adulto, infantil y ambos. Permite invitados.',
            tipoEvento: 'comida',
            fechaEvento: '2026-06-15',
            horaInicio: '14:00:00',
            horaFin: '18:00:00',
            lugar: 'Casal Demo',
            permiteInvitados: true,
            nowSql: $nowSql
        );

        $eventoSinInvitadosId = $this->upsertEvento(
            entidadId: $entidadId,
            slug: 'demo-cena-sin-invitados',
            titulo: 'Cena Demo sin Invitados',
            descripcion: 'Evento demo con menus y actividades, sin permitir invitados.',
            tipoEvento: 'cena',
            fechaEvento: '2026-06-22',
            horaInicio: '21:00:00',
            horaFin: '23:30:00',
            lugar: 'Salon Social Demo',
            permiteInvitados: false,
            nowSql: $nowSql
        );

        // Menus con una o varias opciones para adultos e infantiles.
        $this->upsertActividad(
            eventoId: $eventoConInvitadosId,
            nombre: 'Menu Adulto Tradicional',
            tipoActividad: 'adulto',
            franjaComida: 'comida',
            compatibilidadPersona: 'adulto',
            precioBase: '16.00',
            precioAdultoInterno: '14.00',
            precioAdultoExterno: '18.00',
            precioInfantil: null,
            ordenVisualizacion: 1,
            nowSql: $nowSql
        );

        $this->upsertActividad(
            eventoId: $eventoConInvitadosId,
            nombre: 'Menu Adulto Vegetariano',
            tipoActividad: 'adulto',
            franjaComida: 'comida',
            compatibilidadPersona: 'adulto',
            precioBase: '16.00',
            precioAdultoInterno: '14.00',
            precioAdultoExterno: '18.00',
            precioInfantil: null,
            ordenVisualizacion: 2,
            nowSql: $nowSql
        );

        $this->upsertActividad(
            eventoId: $eventoConInvitadosId,
            nombre: 'Menu Infantil',
            tipoActividad: 'infantil',
            franjaComida: 'comida',
            compatibilidadPersona: 'infantil',
            precioBase: '9.00',
            precioAdultoInterno: null,
            precioAdultoExterno: null,
            precioInfantil: '8.00',
            ordenVisualizacion: 3,
            nowSql: $nowSql
        );

        // Actividades: solo adultos, solo infantiles y para todos.
        $this->upsertActividad(
            eventoId: $eventoConInvitadosId,
            nombre: 'Concurso de Mus',
            tipoActividad: 'especial',
            franjaComida: 'merienda',
            compatibilidadPersona: 'adulto',
            precioBase: '0.00',
            precioAdultoInterno: null,
            precioAdultoExterno: null,
            precioInfantil: null,
            ordenVisualizacion: 4,
            nowSql: $nowSql,
            esDePago: false
        );

        $this->upsertActividad(
            eventoId: $eventoConInvitadosId,
            nombre: 'Gymkana Infantil',
            tipoActividad: 'infantil',
            franjaComida: 'merienda',
            compatibilidadPersona: 'infantil',
            precioBase: '0.00',
            precioAdultoInterno: null,
            precioAdultoExterno: null,
            precioInfantil: null,
            ordenVisualizacion: 5,
            nowSql: $nowSql,
            esDePago: false
        );

        $this->upsertActividad(
            eventoId: $eventoConInvitadosId,
            nombre: 'Baile Popular',
            tipoActividad: 'libre',
            franjaComida: 'merienda',
            compatibilidadPersona: 'ambos',
            precioBase: '0.00',
            precioAdultoInterno: null,
            precioAdultoExterno: null,
            precioInfantil: null,
            ordenVisualizacion: 6,
            nowSql: $nowSql,
            esDePago: false
        );

        // Segundo evento: no admite invitados.
        $this->upsertActividad(
            eventoId: $eventoSinInvitadosId,
            nombre: 'Menu Adulto Unico',
            tipoActividad: 'adulto',
            franjaComida: 'cena',
            compatibilidadPersona: 'adulto',
            precioBase: '20.00',
            precioAdultoInterno: '18.00',
            precioAdultoExterno: '22.00',
            precioInfantil: null,
            ordenVisualizacion: 1,
            nowSql: $nowSql
        );

        $this->upsertActividad(
            eventoId: $eventoSinInvitadosId,
            nombre: 'Menu Infantil Cena',
            tipoActividad: 'infantil',
            franjaComida: 'cena',
            compatibilidadPersona: 'infantil',
            precioBase: '10.00',
            precioAdultoInterno: null,
            precioAdultoExterno: null,
            precioInfantil: '9.00',
            ordenVisualizacion: 2,
            nowSql: $nowSql
        );

        $this->upsertActividad(
            eventoId: $eventoSinInvitadosId,
            nombre: 'Karaoke Familiar',
            tipoActividad: 'libre',
            franjaComida: 'cena',
            compatibilidadPersona: 'ambos',
            precioBase: '0.00',
            precioAdultoInterno: null,
            precioAdultoExterno: null,
            precioInfantil: null,
            ordenVisualizacion: 3,
            nowSql: $nowSql,
            esDePago: false
        );

        // Invitados demo para probar distincion de tipo_persona.
        // A efectos practicos, CADETE suele tratarse como adulto en flujos de inscripcion,
        // pero mantenemos la distincion para cargos mensuales y reparto de tareas.
        $this->upsertInvitado(
            eventoId: $eventoConInvitadosId,
            creadoPorId: $socioId,
            nombre: 'Lucas',
            apellidos: 'Cadete',
            tipoPersona: 'cadete',
            observaciones: 'Invitado cadete demo (distinto de adulto para reportes de cargos).',
            nowSql: $nowSql
        );

        $this->upsertInvitado(
            eventoId: $eventoConInvitadosId,
            creadoPorId: $socioId,
            nombre: 'Nora',
            apellidos: 'Infantil',
            tipoPersona: 'infantil',
            observaciones: 'Invitada infantil demo.',
            nowSql: $nowSql
        );
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Demo data migration is intentionally irreversible to avoid deleting user-generated data linked to these records.');
    }

    private function upsertEvento(
        string $entidadId,
        string $slug,
        string $titulo,
        string $descripcion,
        string $tipoEvento,
        string $fechaEvento,
        string $horaInicio,
        string $horaFin,
        string $lugar,
        bool $permiteInvitados,
        string $nowSql
    ): string {
        $eventoId = $this->connection->fetchOne('SELECT id FROM evento WHERE slug = :slug LIMIT 1', ['slug' => $slug]);

        if (is_string($eventoId) && $eventoId !== '') {
            return $eventoId;
        }

        $eventoId = $this->uuid();
        $this->addSql(
            'INSERT INTO evento (
                id, titulo, slug, descripcion, tipo_evento, fecha_evento, hora_inicio, hora_fin,
                lugar, aforo, fecha_inicio_inscripcion, fecha_fin_inscripcion,
                visible, publicado, admite_pago, permite_invitados, estado,
                requiere_verificacion_acceso, ventana_inicio_verificacion, ventana_fin_verificacion,
                imagen_verificacion, codigo_visual, created_at, updated_at, entidad_id
            ) VALUES (
                :id, :titulo, :slug, :descripcion, :tipo_evento, :fecha_evento, :hora_inicio, :hora_fin,
                :lugar, NULL, :fecha_inicio_inscripcion, :fecha_fin_inscripcion,
                1, 1, 1, :permite_invitados, :estado,
                0, NULL, NULL,
                NULL, NULL, :created_at, :updated_at, :entidad_id
            )',
            [
                'id' => $eventoId,
                'titulo' => $titulo,
                'slug' => $slug,
                'descripcion' => $descripcion,
                'tipo_evento' => $tipoEvento,
                'fecha_evento' => $fechaEvento,
                'hora_inicio' => $horaInicio,
                'hora_fin' => $horaFin,
                'lugar' => $lugar,
                'fecha_inicio_inscripcion' => '2026-05-01 09:00:00',
                'fecha_fin_inscripcion' => '2026-06-30 23:00:00',
                'permite_invitados' => $permiteInvitados ? 1 : 0,
                'estado' => 'publicado',
                'created_at' => $nowSql,
                'updated_at' => $nowSql,
                'entidad_id' => $entidadId,
            ]
        );

        return $eventoId;
    }

    private function upsertActividad(
        string $eventoId,
        string $nombre,
        string $tipoActividad,
        string $franjaComida,
        string $compatibilidadPersona,
        string $precioBase,
        ?string $precioAdultoInterno,
        ?string $precioAdultoExterno,
        ?string $precioInfantil,
        int $ordenVisualizacion,
        string $nowSql,
        bool $esDePago = true
    ): void {
        $existing = $this->connection->fetchOne(
            'SELECT id FROM actividad_evento WHERE evento_id = :evento_id AND nombre = :nombre LIMIT 1',
            [
                'evento_id' => $eventoId,
                'nombre' => $nombre,
            ]
        );

        if (is_string($existing) && $existing !== '') {
            return;
        }

        $this->addSql(
            'INSERT INTO actividad_evento (
                id, nombre, descripcion, tipo_actividad, franja_comida, compatibilidad_persona,
                es_de_pago, precio_base, precio_adulto_interno, precio_adulto_externo, precio_infantil,
                unidades_maximas, orden_visualizacion, activo, confirmacion_automatica,
                observaciones_internas, created_at, updated_at, evento_id
            ) VALUES (
                :id, :nombre, :descripcion, :tipo_actividad, :franja_comida, :compatibilidad_persona,
                :es_de_pago, :precio_base, :precio_adulto_interno, :precio_adulto_externo, :precio_infantil,
                NULL, :orden_visualizacion, 1, 0,
                NULL, :created_at, :updated_at, :evento_id
            )',
            [
                'id' => $this->uuid(),
                'nombre' => $nombre,
                'descripcion' => 'Actividad demo creada desde migracion.',
                'tipo_actividad' => $tipoActividad,
                'franja_comida' => $franjaComida,
                'compatibilidad_persona' => $compatibilidadPersona,
                'es_de_pago' => $esDePago ? 1 : 0,
                'precio_base' => $precioBase,
                'precio_adulto_interno' => $precioAdultoInterno,
                'precio_adulto_externo' => $precioAdultoExterno,
                'precio_infantil' => $precioInfantil,
                'orden_visualizacion' => $ordenVisualizacion,
                'created_at' => $nowSql,
                'updated_at' => $nowSql,
                'evento_id' => $eventoId,
            ]
        );
    }

    private function upsertInvitado(
        string $eventoId,
        string $creadoPorId,
        string $nombre,
        string $apellidos,
        string $tipoPersona,
        string $observaciones,
        string $nowSql
    ): void {
        $existing = $this->connection->fetchOne(
            'SELECT id FROM invitado WHERE evento_id = :evento_id AND creado_por_id = :creado_por_id AND nombre = :nombre AND apellidos = :apellidos LIMIT 1',
            [
                'evento_id' => $eventoId,
                'creado_por_id' => $creadoPorId,
                'nombre' => $nombre,
                'apellidos' => $apellidos,
            ]
        );

        if (is_string($existing) && $existing !== '') {
            return;
        }

        $this->addSql(
            'INSERT INTO invitado (
                id, nombre, apellidos, nombre_completo, tipo_persona, observaciones,
                created_at, deleted_at, creado_por_id, evento_id
            ) VALUES (
                :id, :nombre, :apellidos, :nombre_completo, :tipo_persona, :observaciones,
                :created_at, NULL, :creado_por_id, :evento_id
            )',
            [
                'id' => $this->uuid(),
                'nombre' => $nombre,
                'apellidos' => $apellidos,
                'nombre_completo' => trim($nombre . ' ' . $apellidos),
                'tipo_persona' => $tipoPersona,
                'observaciones' => $observaciones,
                'created_at' => $nowSql,
                'creado_por_id' => $creadoPorId,
                'evento_id' => $eventoId,
            ]
        );
    }

    private function findEntidadIdBySlug(string $slug): ?string
    {
        $id = $this->connection->fetchOne('SELECT id FROM entidad WHERE slug = :slug LIMIT 1', ['slug' => $slug]);

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function findUsuarioIdByEmail(string $email): ?string
    {
        $id = $this->connection->fetchOne('SELECT id FROM usuario WHERE email = :email LIMIT 1', ['email' => $email]);

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function uuid(): string
    {
        return $this->connection->fetchOne('SELECT UUID()');
    }
}

