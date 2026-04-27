<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260427080045 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE actividad_evento (id CHAR(36) NOT NULL, nombre VARCHAR(255) NOT NULL, descripcion LONGTEXT DEFAULT NULL, tipo_actividad VARCHAR(50) NOT NULL, franja_comida VARCHAR(50) NOT NULL, compatibilidad_persona VARCHAR(50) NOT NULL, es_de_pago TINYINT NOT NULL, permite_invitados TINYINT NOT NULL, precio_base DECIMAL(8, 2) NOT NULL, precio_adulto_interno DECIMAL(8, 2) DEFAULT NULL, precio_adulto_externo DECIMAL(8, 2) DEFAULT NULL, precio_infantil DECIMAL(8, 2) DEFAULT NULL, unidades_maximas INT DEFAULT NULL, orden_visualizacion INT NOT NULL, activo TINYINT NOT NULL, confirmacion_automatica TINYINT NOT NULL, observaciones_internas LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, precio_infantil_externo DECIMAL(8, 2) DEFAULT NULL, evento_id CHAR(36) NOT NULL, INDEX IDX_56F6B4CC87A5F842 (evento_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE audit (id CHAR(36) NOT NULL, entity_type VARCHAR(100) NOT NULL, entity_id VARCHAR(255) NOT NULL, actor_id VARCHAR(255) DEFAULT NULL, action VARCHAR(50) NOT NULL, changes JSON DEFAULT NULL, reason VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cargo (id CHAR(36) NOT NULL, nombre VARCHAR(120) NOT NULL, codigo VARCHAR(100) DEFAULT NULL, descripcion LONGTEXT DEFAULT NULL, computa_como_directivo TINYINT DEFAULT 0 NOT NULL, es_representativo TINYINT DEFAULT 0 NOT NULL, es_infantil TINYINT DEFAULT 0 NOT NULL, infantil_especial TINYINT DEFAULT 0 NOT NULL, activo TINYINT DEFAULT 1 NOT NULL, orden_jerarquico SMALLINT DEFAULT 0 NOT NULL, entidad_id CHAR(36) NOT NULL, INDEX IDX_3BEE57716CA204EF (entidad_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cargo_master (id CHAR(36) NOT NULL, nombre VARCHAR(120) NOT NULL, codigo VARCHAR(100) DEFAULT NULL, descripcion LONGTEXT DEFAULT NULL, computa_como_directivo TINYINT DEFAULT 0 NOT NULL, es_representativo TINYINT DEFAULT 0 NOT NULL, es_infantil TINYINT DEFAULT 0 NOT NULL, infantil_especial TINYINT DEFAULT 0 NOT NULL, activo TINYINT DEFAULT 1 NOT NULL, orden_jerarquico SMALLINT DEFAULT 0 NOT NULL, anios_computables DECIMAL(6, 2) DEFAULT \'1.00\' NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cola_correo (id CHAR(36) NOT NULL, destinatario VARCHAR(180) NOT NULL, asunto VARCHAR(255) NOT NULL, plantilla VARCHAR(120) NOT NULL, contexto JSON NOT NULL, estado VARCHAR(20) NOT NULL, intentos INT NOT NULL, ultimo_error LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, enviado_at DATETIME DEFAULT NULL, entidad_id CHAR(36) DEFAULT NULL, usuario_id CHAR(36) DEFAULT NULL, INDEX IDX_3710FF896CA204EF (entidad_id), INDEX IDX_3710FF89DB38439E (usuario_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE entidad (id CHAR(36) NOT NULL, nombre VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, descripcion LONGTEXT DEFAULT NULL, texto_lopd LONGTEXT DEFAULT NULL, terminologia_socio VARCHAR(50) DEFAULT NULL, terminologia_evento VARCHAR(50) DEFAULT NULL, logo VARCHAR(255) DEFAULT NULL, email_contacto VARCHAR(255) NOT NULL, telefono VARCHAR(50) DEFAULT NULL, direccion VARCHAR(255) DEFAULT NULL, codigo_registro VARCHAR(50) NOT NULL, temporada_actual VARCHAR(10) NOT NULL, activa TINYINT NOT NULL, censado TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, usa_reconocimiento TINYINT NOT NULL, tipo_entidad_id CHAR(36) DEFAULT NULL, UNIQUE INDEX UNIQ_4587B0CB989D9B62 (slug), UNIQUE INDEX UNIQ_4587B0CB8DDED420 (codigo_registro), INDEX IDX_4587B0CBFD2406C7 (tipo_entidad_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE entidad_cargo (id CHAR(36) NOT NULL, nombre VARCHAR(120) DEFAULT NULL, orden SMALLINT DEFAULT NULL, activo TINYINT DEFAULT 1 NOT NULL, entidad_id CHAR(36) NOT NULL, cargo_master_id CHAR(36) DEFAULT NULL, cargo_id CHAR(36) DEFAULT NULL, INDEX IDX_DCE19C276CA204EF (entidad_id), INDEX IDX_DCE19C276DA97EEE (cargo_master_id), INDEX IDX_DCE19C27813AC380 (cargo_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE evento (id CHAR(36) NOT NULL, titulo VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, descripcion LONGTEXT DEFAULT NULL, tipo_evento VARCHAR(50) NOT NULL, fecha_evento DATE NOT NULL, hora_inicio TIME DEFAULT NULL, hora_fin TIME DEFAULT NULL, lugar VARCHAR(255) DEFAULT NULL, aforo INT DEFAULT NULL, fecha_inicio_inscripcion DATETIME DEFAULT NULL, fecha_fin_inscripcion DATETIME DEFAULT NULL, visible TINYINT NOT NULL, admite_pago TINYINT NOT NULL, permite_invitados TINYINT NOT NULL, estado VARCHAR(50) NOT NULL, requiere_verificacion_acceso TINYINT NOT NULL, ventana_inicio_verificacion DATETIME DEFAULT NULL, ventana_fin_verificacion DATETIME DEFAULT NULL, imagen_verificacion VARCHAR(255) DEFAULT NULL, codigo_visual VARCHAR(50) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, entidad_id CHAR(36) NOT NULL, INDEX IDX_47860B056CA204EF (entidad_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inscripcion (id CHAR(36) NOT NULL, codigo VARCHAR(50) NOT NULL, estado_inscripcion VARCHAR(50) NOT NULL, estado_pago VARCHAR(50) NOT NULL, importe_total DECIMAL(8, 2) NOT NULL, importe_pagado DECIMAL(8, 2) NOT NULL, moneda VARCHAR(3) NOT NULL, metodo_pago VARCHAR(50) DEFAULT NULL, referencia_pago VARCHAR(100) DEFAULT NULL, fecha_pago DATETIME DEFAULT NULL, observaciones LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, entidad_id CHAR(36) NOT NULL, evento_id CHAR(36) NOT NULL, usuario_id CHAR(36) NOT NULL, INDEX IDX_935E99F06CA204EF (entidad_id), INDEX IDX_935E99F087A5F842 (evento_id), INDEX IDX_935E99F0DB38439E (usuario_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inscripcion_linea (id CHAR(36) NOT NULL, nombre_persona_snapshot VARCHAR(255) NOT NULL, tipo_persona_snapshot VARCHAR(50) NOT NULL, tipo_relacion_economica_snapshot VARCHAR(50) DEFAULT NULL, estado_validacion_snapshot VARCHAR(50) DEFAULT NULL, nombre_actividad_snapshot VARCHAR(255) NOT NULL, franja_comida_snapshot VARCHAR(50) NOT NULL, es_de_pago_snapshot TINYINT NOT NULL, precio_unitario DECIMAL(8, 2) NOT NULL, estado_linea VARCHAR(50) NOT NULL, pagada TINYINT DEFAULT 0 NOT NULL, observaciones LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, inscripcion_id CHAR(36) NOT NULL, invitado_id CHAR(36) DEFAULT NULL, usuario_id CHAR(36) DEFAULT NULL, actividad_id CHAR(36) NOT NULL, INDEX IDX_2B833302FFD5FBD3 (inscripcion_id), INDEX IDX_2B8333028E552E60 (invitado_id), INDEX IDX_2B833302DB38439E (usuario_id), INDEX IDX_2B8333026014FACA (actividad_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE invitado (id CHAR(36) NOT NULL, nombre VARCHAR(255) NOT NULL, apellidos VARCHAR(255) NOT NULL, nombre_completo VARCHAR(255) NOT NULL, tipo_persona VARCHAR(50) NOT NULL, observaciones LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, creado_por_id CHAR(36) NOT NULL, evento_id CHAR(36) NOT NULL, INDEX IDX_4982EC17FE35D8C4 (creado_por_id), INDEX IDX_4982EC1787A5F842 (evento_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE pago (id CHAR(36) NOT NULL, fecha DATETIME NOT NULL, importe DECIMAL(8, 2) NOT NULL, metodo_pago VARCHAR(50) NOT NULL, referencia VARCHAR(100) DEFAULT NULL, estado VARCHAR(20) NOT NULL, observaciones LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, inscripcion_id CHAR(36) NOT NULL, registrado_por_id CHAR(36) NOT NULL, INDEX IDX_F4DF5F3EFFD5FBD3 (inscripcion_id), INDEX IDX_F4DF5F3EEC7D893C (registrado_por_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reconocimiento (id CHAR(36) NOT NULL, codigo VARCHAR(100) NOT NULL, nombre VARCHAR(150) NOT NULL, tipo VARCHAR(50) NOT NULL, orden SMALLINT NOT NULL, min_antiguedad DECIMAL(6, 2) DEFAULT NULL, min_antiguedad_directivo DECIMAL(6, 2) DEFAULT NULL, requiere_directivo TINYINT DEFAULT 0 NOT NULL, requiere_anterior TINYINT DEFAULT 0 NOT NULL, activo TINYINT DEFAULT 1 NOT NULL, entidad_id CHAR(36) NOT NULL, INDEX IDX_A19F47246CA204EF (entidad_id), UNIQUE INDEX uniq_reconocimiento_entidad_codigo (entidad_id, codigo), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE relacion_usuario (id CHAR(36) NOT NULL, tipo_relacion VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, usuario_origen_id CHAR(36) NOT NULL, usuario_destino_id CHAR(36) NOT NULL, INDEX IDX_439EA65F1A6974DF (usuario_origen_id), INDEX IDX_439EA65F17064CB7 (usuario_destino_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE seleccion_participante_evento (id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, evento_id CHAR(36) NOT NULL, inscrito_por_usuario_id CHAR(36) NOT NULL, usuario_id CHAR(36) DEFAULT NULL, invitado_id CHAR(36) DEFAULT NULL, INDEX IDX_A0C62AA187A5F842 (evento_id), INDEX IDX_A0C62AA1585E2F0 (inscrito_por_usuario_id), INDEX IDX_A0C62AA1DB38439E (usuario_id), INDEX IDX_A0C62AA18E552E60 (invitado_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE seleccion_participante_evento_linea (id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, seleccion_participante_evento_id CHAR(36) NOT NULL, evento_id CHAR(36) NOT NULL, usuario_id CHAR(36) DEFAULT NULL, invitado_id CHAR(36) DEFAULT NULL, actividad_id CHAR(36) NOT NULL, inscripcion_linea_id CHAR(36) DEFAULT NULL, INDEX IDX_E5EF0816A397596A (seleccion_participante_evento_id), INDEX IDX_E5EF081687A5F842 (evento_id), INDEX IDX_E5EF0816DB38439E (usuario_id), INDEX IDX_E5EF08168E552E60 (invitado_id), INDEX IDX_E5EF08166014FACA (actividad_id), INDEX IDX_E5EF0816E51FA6DF (inscripcion_linea_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE temporada_entidad (id CHAR(36) NOT NULL, codigo VARCHAR(20) NOT NULL, nombre VARCHAR(120) DEFAULT NULL, fecha_inicio DATE DEFAULT NULL, fecha_fin DATE DEFAULT NULL, cerrada TINYINT DEFAULT 0 NOT NULL, entidad_id CHAR(36) NOT NULL, INDEX IDX_7E452AF06CA204EF (entidad_id), UNIQUE INDEX uniq_temporada_entidad_codigo (entidad_id, codigo), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tipo_entidad (id CHAR(36) NOT NULL, codigo VARCHAR(50) NOT NULL, nombre VARCHAR(150) NOT NULL, descripcion LONGTEXT DEFAULT NULL, activo TINYINT DEFAULT 1 NOT NULL, UNIQUE INDEX UNIQ_567E916B20332D99 (codigo), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tipo_entidad_cargo (id CHAR(36) NOT NULL, activo TINYINT DEFAULT 1 NOT NULL, tipo_entidad_id CHAR(36) NOT NULL, cargo_master_id CHAR(36) NOT NULL, INDEX IDX_DC52CF79FD2406C7 (tipo_entidad_id), INDEX IDX_DC52CF796DA97EEE (cargo_master_id), UNIQUE INDEX uniq_tipo_entidad_cargo (tipo_entidad_id, cargo_master_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE usuario (id CHAR(36) NOT NULL, nombre VARCHAR(100) NOT NULL, apellidos VARCHAR(150) NOT NULL, nombre_completo VARCHAR(255) NOT NULL, email VARCHAR(180) NOT NULL, telefono VARCHAR(50) DEFAULT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, activo TINYINT NOT NULL, estado_validacion VARCHAR(50) NOT NULL, tipo_usuario_economico VARCHAR(50) NOT NULL, tipo_persona VARCHAR(50) NOT NULL, censado_via VARCHAR(50) DEFAULT NULL, antiguedad SMALLINT DEFAULT NULL, antiguedad_real SMALLINT DEFAULT NULL, forma_pago_preferida VARCHAR(50) DEFAULT NULL, debe_cambiar_password TINYINT NOT NULL, password_actualizada_at DATETIME DEFAULT NULL, fecha_alta_censo DATETIME DEFAULT NULL, fecha_baja_censo DATETIME DEFAULT NULL, motivo_baja_censo LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, fecha_nacimiento DATE DEFAULT NULL, acepto_lopd TINYINT NOT NULL, acepto_lopd_at DATETIME DEFAULT NULL, entidad_id CHAR(36) NOT NULL, UNIQUE INDEX UNIQ_2265B05DE7927C74 (email), INDEX IDX_2265B05D6CA204EF (entidad_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE usuario_reconocimiento (id CHAR(36) NOT NULL, temporada_codigo VARCHAR(20) DEFAULT NULL, fecha_concesion DATE DEFAULT NULL, observaciones LONGTEXT DEFAULT NULL, usuario_id CHAR(36) NOT NULL, entidad_id CHAR(36) NOT NULL, reconocimiento_id CHAR(36) NOT NULL, INDEX IDX_C0020BE9DB38439E (usuario_id), INDEX IDX_C0020BE96CA204EF (entidad_id), INDEX IDX_C0020BE9868D7CBA (reconocimiento_id), UNIQUE INDEX uniq_usuario_reconocimiento (usuario_id, reconocimiento_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE usuario_temporada_cargo (id CHAR(36) NOT NULL, principal TINYINT DEFAULT 0 NOT NULL, computa_antiguedad TINYINT DEFAULT 1 NOT NULL, computa_reconocimiento TINYINT DEFAULT 1 NOT NULL, anios_extra_aplicados DECIMAL(6, 2) DEFAULT \'0.00\' NOT NULL, tipo_persona VARCHAR(20) NOT NULL, orden SMALLINT DEFAULT 0 NOT NULL, observaciones LONGTEXT DEFAULT NULL, usuario_id CHAR(36) NOT NULL, temporada_id CHAR(36) NOT NULL, entidad_cargo_id CHAR(36) NOT NULL, INDEX IDX_D850141BDB38439E (usuario_id), INDEX IDX_D850141B6E1CF8A8 (temporada_id), INDEX IDX_D850141B51BA8271 (entidad_cargo_id), UNIQUE INDEX uniq_usuario_temporada_cargo (usuario_id, temporada_id, entidad_cargo_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE actividad_evento ADD CONSTRAINT FK_56F6B4CC87A5F842 FOREIGN KEY (evento_id) REFERENCES evento (id)');
        $this->addSql('ALTER TABLE cargo ADD CONSTRAINT FK_3BEE57716CA204EF FOREIGN KEY (entidad_id) REFERENCES entidad (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cola_correo ADD CONSTRAINT FK_3710FF896CA204EF FOREIGN KEY (entidad_id) REFERENCES entidad (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cola_correo ADD CONSTRAINT FK_3710FF89DB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE entidad ADD CONSTRAINT FK_4587B0CBFD2406C7 FOREIGN KEY (tipo_entidad_id) REFERENCES tipo_entidad (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE entidad_cargo ADD CONSTRAINT FK_DCE19C276CA204EF FOREIGN KEY (entidad_id) REFERENCES entidad (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE entidad_cargo ADD CONSTRAINT FK_DCE19C276DA97EEE FOREIGN KEY (cargo_master_id) REFERENCES cargo_master (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE entidad_cargo ADD CONSTRAINT FK_DCE19C27813AC380 FOREIGN KEY (cargo_id) REFERENCES cargo (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE evento ADD CONSTRAINT FK_47860B056CA204EF FOREIGN KEY (entidad_id) REFERENCES entidad (id)');
        $this->addSql('ALTER TABLE inscripcion ADD CONSTRAINT FK_935E99F06CA204EF FOREIGN KEY (entidad_id) REFERENCES entidad (id)');
        $this->addSql('ALTER TABLE inscripcion ADD CONSTRAINT FK_935E99F087A5F842 FOREIGN KEY (evento_id) REFERENCES evento (id)');
        $this->addSql('ALTER TABLE inscripcion ADD CONSTRAINT FK_935E99F0DB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id)');
        $this->addSql('ALTER TABLE inscripcion_linea ADD CONSTRAINT FK_2B833302FFD5FBD3 FOREIGN KEY (inscripcion_id) REFERENCES inscripcion (id)');
        $this->addSql('ALTER TABLE inscripcion_linea ADD CONSTRAINT FK_2B8333028E552E60 FOREIGN KEY (invitado_id) REFERENCES invitado (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE inscripcion_linea ADD CONSTRAINT FK_2B833302DB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE inscripcion_linea ADD CONSTRAINT FK_2B8333026014FACA FOREIGN KEY (actividad_id) REFERENCES actividad_evento (id)');
        $this->addSql('ALTER TABLE invitado ADD CONSTRAINT FK_4982EC17FE35D8C4 FOREIGN KEY (creado_por_id) REFERENCES usuario (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE invitado ADD CONSTRAINT FK_4982EC1787A5F842 FOREIGN KEY (evento_id) REFERENCES evento (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pago ADD CONSTRAINT FK_F4DF5F3EFFD5FBD3 FOREIGN KEY (inscripcion_id) REFERENCES inscripcion (id)');
        $this->addSql('ALTER TABLE pago ADD CONSTRAINT FK_F4DF5F3EEC7D893C FOREIGN KEY (registrado_por_id) REFERENCES usuario (id)');
        $this->addSql('ALTER TABLE reconocimiento ADD CONSTRAINT FK_A19F47246CA204EF FOREIGN KEY (entidad_id) REFERENCES entidad (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE relacion_usuario ADD CONSTRAINT FK_439EA65F1A6974DF FOREIGN KEY (usuario_origen_id) REFERENCES usuario (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE relacion_usuario ADD CONSTRAINT FK_439EA65F17064CB7 FOREIGN KEY (usuario_destino_id) REFERENCES usuario (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE seleccion_participante_evento ADD CONSTRAINT FK_A0C62AA187A5F842 FOREIGN KEY (evento_id) REFERENCES evento (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE seleccion_participante_evento ADD CONSTRAINT FK_A0C62AA1585E2F0 FOREIGN KEY (inscrito_por_usuario_id) REFERENCES usuario (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE seleccion_participante_evento ADD CONSTRAINT FK_A0C62AA1DB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE seleccion_participante_evento ADD CONSTRAINT FK_A0C62AA18E552E60 FOREIGN KEY (invitado_id) REFERENCES invitado (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE seleccion_participante_evento_linea ADD CONSTRAINT FK_E5EF0816A397596A FOREIGN KEY (seleccion_participante_evento_id) REFERENCES seleccion_participante_evento (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE seleccion_participante_evento_linea ADD CONSTRAINT FK_E5EF081687A5F842 FOREIGN KEY (evento_id) REFERENCES evento (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE seleccion_participante_evento_linea ADD CONSTRAINT FK_E5EF0816DB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE seleccion_participante_evento_linea ADD CONSTRAINT FK_E5EF08168E552E60 FOREIGN KEY (invitado_id) REFERENCES invitado (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE seleccion_participante_evento_linea ADD CONSTRAINT FK_E5EF08166014FACA FOREIGN KEY (actividad_id) REFERENCES actividad_evento (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE seleccion_participante_evento_linea ADD CONSTRAINT FK_E5EF0816E51FA6DF FOREIGN KEY (inscripcion_linea_id) REFERENCES inscripcion_linea (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE temporada_entidad ADD CONSTRAINT FK_7E452AF06CA204EF FOREIGN KEY (entidad_id) REFERENCES entidad (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tipo_entidad_cargo ADD CONSTRAINT FK_DC52CF79FD2406C7 FOREIGN KEY (tipo_entidad_id) REFERENCES tipo_entidad (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tipo_entidad_cargo ADD CONSTRAINT FK_DC52CF796DA97EEE FOREIGN KEY (cargo_master_id) REFERENCES cargo_master (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE usuario ADD CONSTRAINT FK_2265B05D6CA204EF FOREIGN KEY (entidad_id) REFERENCES entidad (id)');
        $this->addSql('ALTER TABLE usuario_reconocimiento ADD CONSTRAINT FK_C0020BE9DB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE usuario_reconocimiento ADD CONSTRAINT FK_C0020BE96CA204EF FOREIGN KEY (entidad_id) REFERENCES entidad (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE usuario_reconocimiento ADD CONSTRAINT FK_C0020BE9868D7CBA FOREIGN KEY (reconocimiento_id) REFERENCES reconocimiento (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE usuario_temporada_cargo ADD CONSTRAINT FK_D850141BDB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE usuario_temporada_cargo ADD CONSTRAINT FK_D850141B6E1CF8A8 FOREIGN KEY (temporada_id) REFERENCES temporada_entidad (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE usuario_temporada_cargo ADD CONSTRAINT FK_D850141B51BA8271 FOREIGN KEY (entidad_cargo_id) REFERENCES entidad_cargo (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE actividad_evento DROP FOREIGN KEY FK_56F6B4CC87A5F842');
        $this->addSql('ALTER TABLE cargo DROP FOREIGN KEY FK_3BEE57716CA204EF');
        $this->addSql('ALTER TABLE cola_correo DROP FOREIGN KEY FK_3710FF896CA204EF');
        $this->addSql('ALTER TABLE cola_correo DROP FOREIGN KEY FK_3710FF89DB38439E');
        $this->addSql('ALTER TABLE entidad DROP FOREIGN KEY FK_4587B0CBFD2406C7');
        $this->addSql('ALTER TABLE entidad_cargo DROP FOREIGN KEY FK_DCE19C276CA204EF');
        $this->addSql('ALTER TABLE entidad_cargo DROP FOREIGN KEY FK_DCE19C276DA97EEE');
        $this->addSql('ALTER TABLE entidad_cargo DROP FOREIGN KEY FK_DCE19C27813AC380');
        $this->addSql('ALTER TABLE evento DROP FOREIGN KEY FK_47860B056CA204EF');
        $this->addSql('ALTER TABLE inscripcion DROP FOREIGN KEY FK_935E99F06CA204EF');
        $this->addSql('ALTER TABLE inscripcion DROP FOREIGN KEY FK_935E99F087A5F842');
        $this->addSql('ALTER TABLE inscripcion DROP FOREIGN KEY FK_935E99F0DB38439E');
        $this->addSql('ALTER TABLE inscripcion_linea DROP FOREIGN KEY FK_2B833302FFD5FBD3');
        $this->addSql('ALTER TABLE inscripcion_linea DROP FOREIGN KEY FK_2B8333028E552E60');
        $this->addSql('ALTER TABLE inscripcion_linea DROP FOREIGN KEY FK_2B833302DB38439E');
        $this->addSql('ALTER TABLE inscripcion_linea DROP FOREIGN KEY FK_2B8333026014FACA');
        $this->addSql('ALTER TABLE invitado DROP FOREIGN KEY FK_4982EC17FE35D8C4');
        $this->addSql('ALTER TABLE invitado DROP FOREIGN KEY FK_4982EC1787A5F842');
        $this->addSql('ALTER TABLE pago DROP FOREIGN KEY FK_F4DF5F3EFFD5FBD3');
        $this->addSql('ALTER TABLE pago DROP FOREIGN KEY FK_F4DF5F3EEC7D893C');
        $this->addSql('ALTER TABLE reconocimiento DROP FOREIGN KEY FK_A19F47246CA204EF');
        $this->addSql('ALTER TABLE relacion_usuario DROP FOREIGN KEY FK_439EA65F1A6974DF');
        $this->addSql('ALTER TABLE relacion_usuario DROP FOREIGN KEY FK_439EA65F17064CB7');
        $this->addSql('ALTER TABLE seleccion_participante_evento DROP FOREIGN KEY FK_A0C62AA187A5F842');
        $this->addSql('ALTER TABLE seleccion_participante_evento DROP FOREIGN KEY FK_A0C62AA1585E2F0');
        $this->addSql('ALTER TABLE seleccion_participante_evento DROP FOREIGN KEY FK_A0C62AA1DB38439E');
        $this->addSql('ALTER TABLE seleccion_participante_evento DROP FOREIGN KEY FK_A0C62AA18E552E60');
        $this->addSql('ALTER TABLE seleccion_participante_evento_linea DROP FOREIGN KEY FK_E5EF0816A397596A');
        $this->addSql('ALTER TABLE seleccion_participante_evento_linea DROP FOREIGN KEY FK_E5EF081687A5F842');
        $this->addSql('ALTER TABLE seleccion_participante_evento_linea DROP FOREIGN KEY FK_E5EF0816DB38439E');
        $this->addSql('ALTER TABLE seleccion_participante_evento_linea DROP FOREIGN KEY FK_E5EF08168E552E60');
        $this->addSql('ALTER TABLE seleccion_participante_evento_linea DROP FOREIGN KEY FK_E5EF08166014FACA');
        $this->addSql('ALTER TABLE seleccion_participante_evento_linea DROP FOREIGN KEY FK_E5EF0816E51FA6DF');
        $this->addSql('ALTER TABLE temporada_entidad DROP FOREIGN KEY FK_7E452AF06CA204EF');
        $this->addSql('ALTER TABLE tipo_entidad_cargo DROP FOREIGN KEY FK_DC52CF79FD2406C7');
        $this->addSql('ALTER TABLE tipo_entidad_cargo DROP FOREIGN KEY FK_DC52CF796DA97EEE');
        $this->addSql('ALTER TABLE usuario DROP FOREIGN KEY FK_2265B05D6CA204EF');
        $this->addSql('ALTER TABLE usuario_reconocimiento DROP FOREIGN KEY FK_C0020BE9DB38439E');
        $this->addSql('ALTER TABLE usuario_reconocimiento DROP FOREIGN KEY FK_C0020BE96CA204EF');
        $this->addSql('ALTER TABLE usuario_reconocimiento DROP FOREIGN KEY FK_C0020BE9868D7CBA');
        $this->addSql('ALTER TABLE usuario_temporada_cargo DROP FOREIGN KEY FK_D850141BDB38439E');
        $this->addSql('ALTER TABLE usuario_temporada_cargo DROP FOREIGN KEY FK_D850141B6E1CF8A8');
        $this->addSql('ALTER TABLE usuario_temporada_cargo DROP FOREIGN KEY FK_D850141B51BA8271');
        $this->addSql('DROP TABLE actividad_evento');
        $this->addSql('DROP TABLE audit');
        $this->addSql('DROP TABLE cargo');
        $this->addSql('DROP TABLE cargo_master');
        $this->addSql('DROP TABLE cola_correo');
        $this->addSql('DROP TABLE entidad');
        $this->addSql('DROP TABLE entidad_cargo');
        $this->addSql('DROP TABLE evento');
        $this->addSql('DROP TABLE inscripcion');
        $this->addSql('DROP TABLE inscripcion_linea');
        $this->addSql('DROP TABLE invitado');
        $this->addSql('DROP TABLE pago');
        $this->addSql('DROP TABLE reconocimiento');
        $this->addSql('DROP TABLE relacion_usuario');
        $this->addSql('DROP TABLE seleccion_participante_evento');
        $this->addSql('DROP TABLE seleccion_participante_evento_linea');
        $this->addSql('DROP TABLE temporada_entidad');
        $this->addSql('DROP TABLE tipo_entidad');
        $this->addSql('DROP TABLE tipo_entidad_cargo');
        $this->addSql('DROP TABLE usuario');
        $this->addSql('DROP TABLE usuario_reconocimiento');
        $this->addSql('DROP TABLE usuario_temporada_cargo');
    }
}
