<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260402102722 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE censo_entrada (id CHAR(36) NOT NULL, nombre VARCHAR(100) NOT NULL, apellidos VARCHAR(150) NOT NULL, email VARCHAR(180) DEFAULT NULL, dni VARCHAR(20) DEFAULT NULL, parentesco VARCHAR(50) NOT NULL, tipo_persona VARCHAR(50) NOT NULL, tipo_relacion_economica VARCHAR(50) NOT NULL, temporada VARCHAR(10) NOT NULL, procesado TINYINT NOT NULL, created_at DATETIME NOT NULL, entidad_id CHAR(36) NOT NULL, usuario_vinculado_id CHAR(36) DEFAULT NULL, INDEX IDX_4899BABA6CA204EF (entidad_id), INDEX IDX_4899BABAC63A09D1 (usuario_vinculado_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE entidad (id CHAR(36) NOT NULL, nombre VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, descripcion LONGTEXT DEFAULT NULL, tipo_entidad VARCHAR(50) NOT NULL, terminologia_socio VARCHAR(50) DEFAULT NULL, terminologia_evento VARCHAR(50) DEFAULT NULL, logo VARCHAR(255) DEFAULT NULL, email_contacto VARCHAR(255) NOT NULL, telefono VARCHAR(50) DEFAULT NULL, direccion VARCHAR(255) DEFAULT NULL, codigo_registro VARCHAR(50) NOT NULL, temporada_actual VARCHAR(10) NOT NULL, activa TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_4587B0CB989D9B62 (slug), UNIQUE INDEX UNIQ_4587B0CB8DDED420 (codigo_registro), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE entidad_admins (entidad_id CHAR(36) NOT NULL, usuario_id CHAR(36) NOT NULL, INDEX IDX_200FEE056CA204EF (entidad_id), INDEX IDX_200FEE05DB38439E (usuario_id), PRIMARY KEY (entidad_id, usuario_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE evento (id CHAR(36) NOT NULL, titulo VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, descripcion LONGTEXT DEFAULT NULL, tipo_evento VARCHAR(50) NOT NULL, fecha_evento DATE NOT NULL, hora_inicio TIME DEFAULT NULL, hora_fin TIME DEFAULT NULL, lugar VARCHAR(255) DEFAULT NULL, aforo INT DEFAULT NULL, fecha_inicio_inscripcion DATETIME NOT NULL, fecha_fin_inscripcion DATETIME NOT NULL, visible TINYINT NOT NULL, publicado TINYINT NOT NULL, admite_pago TINYINT NOT NULL, estado VARCHAR(50) NOT NULL, requiere_verificacion_acceso TINYINT NOT NULL, ventana_inicio_verificacion DATETIME DEFAULT NULL, ventana_fin_verificacion DATETIME DEFAULT NULL, imagen_verificacion VARCHAR(255) DEFAULT NULL, codigo_visual VARCHAR(50) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, entidad_id CHAR(36) NOT NULL, INDEX IDX_47860B056CA204EF (entidad_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inscripcion (id CHAR(36) NOT NULL, codigo VARCHAR(50) NOT NULL, estado_inscripcion VARCHAR(50) NOT NULL, estado_pago VARCHAR(50) NOT NULL, importe_total NUMERIC(8, 2) NOT NULL, importe_pagado NUMERIC(8, 2) NOT NULL, moneda VARCHAR(3) NOT NULL, metodo_pago VARCHAR(50) DEFAULT NULL, referencia_pago VARCHAR(100) DEFAULT NULL, fecha_pago DATETIME DEFAULT NULL, observaciones LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, entidad_id CHAR(36) NOT NULL, evento_id CHAR(36) NOT NULL, usuario_id CHAR(36) NOT NULL, INDEX IDX_935E99F06CA204EF (entidad_id), INDEX IDX_935E99F087A5F842 (evento_id), INDEX IDX_935E99F0DB38439E (usuario_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inscripcion_linea (id CHAR(36) NOT NULL, nombre_persona_snapshot VARCHAR(255) NOT NULL, tipo_persona_snapshot VARCHAR(50) NOT NULL, tipo_relacion_economica_snapshot VARCHAR(50) DEFAULT NULL, estado_validacion_snapshot VARCHAR(50) DEFAULT NULL, nombre_menu_snapshot VARCHAR(255) NOT NULL, franja_comida_snapshot VARCHAR(50) NOT NULL, es_de_pago_snapshot TINYINT NOT NULL, precio_unitario NUMERIC(8, 2) NOT NULL, estado_linea VARCHAR(50) NOT NULL, observaciones LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, inscripcion_id CHAR(36) NOT NULL, invitado_id CHAR(36) DEFAULT NULL, usuario_id CHAR(36) DEFAULT NULL, menu_id CHAR(36) NOT NULL, INDEX IDX_2B833302FFD5FBD3 (inscripcion_id), INDEX IDX_2B8333028E552E60 (invitado_id), INDEX IDX_2B833302DB38439E (usuario_id), INDEX IDX_2B833302CCD7E912 (menu_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE invitado (id CHAR(36) NOT NULL, nombre VARCHAR(255) NOT NULL, apellidos VARCHAR(255) NOT NULL, nombre_completo VARCHAR(255) NOT NULL, tipo_persona VARCHAR(50) NOT NULL, observaciones LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, creado_por_id CHAR(36) NOT NULL, evento_id CHAR(36) NOT NULL, INDEX IDX_4982EC17FE35D8C4 (creado_por_id), INDEX IDX_4982EC1787A5F842 (evento_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE menu_evento (id CHAR(36) NOT NULL, nombre VARCHAR(255) NOT NULL, descripcion LONGTEXT DEFAULT NULL, tipo_menu VARCHAR(50) NOT NULL, franja_comida VARCHAR(50) NOT NULL, compatibilidad_persona VARCHAR(50) NOT NULL, es_de_pago TINYINT NOT NULL, precio_base NUMERIC(8, 2) NOT NULL, precio_adulto_interno NUMERIC(8, 2) DEFAULT NULL, precio_adulto_externo NUMERIC(8, 2) DEFAULT NULL, precio_infantil NUMERIC(8, 2) DEFAULT NULL, unidades_maximas INT DEFAULT NULL, orden_visualizacion INT NOT NULL, activo TINYINT NOT NULL, confirmacion_automatica TINYINT NOT NULL, observaciones_internas LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, evento_id CHAR(36) NOT NULL, INDEX IDX_94C153DD87A5F842 (evento_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE pago (id CHAR(36) NOT NULL, fecha DATETIME NOT NULL, importe NUMERIC(8, 2) NOT NULL, metodo_pago VARCHAR(50) NOT NULL, referencia VARCHAR(100) DEFAULT NULL, estado VARCHAR(20) NOT NULL, observaciones LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, inscripcion_id CHAR(36) NOT NULL, registrado_por_id CHAR(36) NOT NULL, INDEX IDX_F4DF5F3EFFD5FBD3 (inscripcion_id), INDEX IDX_F4DF5F3EEC7D893C (registrado_por_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE relacion_usuario (id CHAR(36) NOT NULL, tipo_relacion VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, usuario_origen_id CHAR(36) NOT NULL, usuario_destino_id CHAR(36) NOT NULL, INDEX IDX_439EA65F1A6974DF (usuario_origen_id), INDEX IDX_439EA65F17064CB7 (usuario_destino_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE seleccion_participante_evento (id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, evento_id CHAR(36) NOT NULL, inscrito_por_usuario_id CHAR(36) NOT NULL, usuario_id CHAR(36) DEFAULT NULL, invitado_id CHAR(36) DEFAULT NULL, INDEX IDX_A0C62AA187A5F842 (evento_id), INDEX IDX_A0C62AA1585E2F0 (inscrito_por_usuario_id), INDEX IDX_A0C62AA1DB38439E (usuario_id), INDEX IDX_A0C62AA18E552E60 (invitado_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE seleccion_participante_evento_linea (id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, seleccion_participante_evento_id CHAR(36) NOT NULL, evento_id CHAR(36) NOT NULL, usuario_id CHAR(36) DEFAULT NULL, invitado_id CHAR(36) DEFAULT NULL, menu_id CHAR(36) NOT NULL, inscripcion_linea_id CHAR(36) DEFAULT NULL, INDEX IDX_E5EF0816A397596A (seleccion_participante_evento_id), INDEX IDX_E5EF081687A5F842 (evento_id), INDEX IDX_E5EF0816DB38439E (usuario_id), INDEX IDX_E5EF08168E552E60 (invitado_id), INDEX IDX_E5EF0816CCD7E912 (menu_id), INDEX IDX_E5EF0816E51FA6DF (inscripcion_linea_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE usuario (id CHAR(36) NOT NULL, nombre VARCHAR(100) NOT NULL, apellidos VARCHAR(150) NOT NULL, nombre_completo VARCHAR(255) NOT NULL, email VARCHAR(180) NOT NULL, telefono VARCHAR(50) DEFAULT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, activo TINYINT NOT NULL, tipo_usuario_economico VARCHAR(50) NOT NULL, estado_validacion VARCHAR(50) NOT NULL, es_censado_interno TINYINT NOT NULL, codigo_registro_usado VARCHAR(50) DEFAULT NULL, censado_via VARCHAR(50) DEFAULT NULL, fecha_solicitud_alta DATETIME DEFAULT NULL, fecha_alta_censo DATETIME DEFAULT NULL, fecha_baja_censo DATETIME DEFAULT NULL, motivo_baja_censo LONGTEXT DEFAULT NULL, fecha_validacion DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, fecha_nacimiento DATE DEFAULT NULL, entidad_id CHAR(36) NOT NULL, censo_entrada_ref_id CHAR(36) DEFAULT NULL, validado_por_id CHAR(36) DEFAULT NULL, UNIQUE INDEX UNIQ_2265B05DE7927C74 (email), INDEX IDX_2265B05D6CA204EF (entidad_id), INDEX IDX_2265B05D6566B364 (censo_entrada_ref_id), INDEX IDX_2265B05D8892BEA7 (validado_por_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE censo_entrada ADD CONSTRAINT FK_4899BABA6CA204EF FOREIGN KEY (entidad_id) REFERENCES entidad (id)');
        $this->addSql('ALTER TABLE censo_entrada ADD CONSTRAINT FK_4899BABAC63A09D1 FOREIGN KEY (usuario_vinculado_id) REFERENCES usuario (id)');
        $this->addSql('ALTER TABLE entidad_admins ADD CONSTRAINT FK_200FEE056CA204EF FOREIGN KEY (entidad_id) REFERENCES entidad (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE entidad_admins ADD CONSTRAINT FK_200FEE05DB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE evento ADD CONSTRAINT FK_47860B056CA204EF FOREIGN KEY (entidad_id) REFERENCES entidad (id)');
        $this->addSql('ALTER TABLE inscripcion ADD CONSTRAINT FK_935E99F06CA204EF FOREIGN KEY (entidad_id) REFERENCES entidad (id)');
        $this->addSql('ALTER TABLE inscripcion ADD CONSTRAINT FK_935E99F087A5F842 FOREIGN KEY (evento_id) REFERENCES evento (id)');
        $this->addSql('ALTER TABLE inscripcion ADD CONSTRAINT FK_935E99F0DB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id)');
        $this->addSql('ALTER TABLE inscripcion_linea ADD CONSTRAINT FK_2B833302FFD5FBD3 FOREIGN KEY (inscripcion_id) REFERENCES inscripcion (id)');
        $this->addSql('ALTER TABLE inscripcion_linea ADD CONSTRAINT FK_2B8333028E552E60 FOREIGN KEY (invitado_id) REFERENCES invitado (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE inscripcion_linea ADD CONSTRAINT FK_2B833302DB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE inscripcion_linea ADD CONSTRAINT FK_2B833302CCD7E912 FOREIGN KEY (menu_id) REFERENCES menu_evento (id)');
        $this->addSql('ALTER TABLE invitado ADD CONSTRAINT FK_4982EC17FE35D8C4 FOREIGN KEY (creado_por_id) REFERENCES usuario (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE invitado ADD CONSTRAINT FK_4982EC1787A5F842 FOREIGN KEY (evento_id) REFERENCES evento (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_evento ADD CONSTRAINT FK_94C153DD87A5F842 FOREIGN KEY (evento_id) REFERENCES evento (id)');
        $this->addSql('ALTER TABLE pago ADD CONSTRAINT FK_F4DF5F3EFFD5FBD3 FOREIGN KEY (inscripcion_id) REFERENCES inscripcion (id)');
        $this->addSql('ALTER TABLE pago ADD CONSTRAINT FK_F4DF5F3EEC7D893C FOREIGN KEY (registrado_por_id) REFERENCES usuario (id)');
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
        $this->addSql('ALTER TABLE seleccion_participante_evento_linea ADD CONSTRAINT FK_E5EF0816CCD7E912 FOREIGN KEY (menu_id) REFERENCES menu_evento (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE seleccion_participante_evento_linea ADD CONSTRAINT FK_E5EF0816E51FA6DF FOREIGN KEY (inscripcion_linea_id) REFERENCES inscripcion_linea (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE usuario ADD CONSTRAINT FK_2265B05D6CA204EF FOREIGN KEY (entidad_id) REFERENCES entidad (id)');
        $this->addSql('ALTER TABLE usuario ADD CONSTRAINT FK_2265B05D6566B364 FOREIGN KEY (censo_entrada_ref_id) REFERENCES usuario (id)');
        $this->addSql('ALTER TABLE usuario ADD CONSTRAINT FK_2265B05D8892BEA7 FOREIGN KEY (validado_por_id) REFERENCES usuario (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE censo_entrada DROP FOREIGN KEY FK_4899BABA6CA204EF');
        $this->addSql('ALTER TABLE censo_entrada DROP FOREIGN KEY FK_4899BABAC63A09D1');
        $this->addSql('ALTER TABLE entidad_admins DROP FOREIGN KEY FK_200FEE056CA204EF');
        $this->addSql('ALTER TABLE entidad_admins DROP FOREIGN KEY FK_200FEE05DB38439E');
        $this->addSql('ALTER TABLE evento DROP FOREIGN KEY FK_47860B056CA204EF');
        $this->addSql('ALTER TABLE inscripcion DROP FOREIGN KEY FK_935E99F06CA204EF');
        $this->addSql('ALTER TABLE inscripcion DROP FOREIGN KEY FK_935E99F087A5F842');
        $this->addSql('ALTER TABLE inscripcion DROP FOREIGN KEY FK_935E99F0DB38439E');
        $this->addSql('ALTER TABLE inscripcion_linea DROP FOREIGN KEY FK_2B833302FFD5FBD3');
        $this->addSql('ALTER TABLE inscripcion_linea DROP FOREIGN KEY FK_2B8333028E552E60');
        $this->addSql('ALTER TABLE inscripcion_linea DROP FOREIGN KEY FK_2B833302DB38439E');
        $this->addSql('ALTER TABLE inscripcion_linea DROP FOREIGN KEY FK_2B833302CCD7E912');
        $this->addSql('ALTER TABLE invitado DROP FOREIGN KEY FK_4982EC17FE35D8C4');
        $this->addSql('ALTER TABLE invitado DROP FOREIGN KEY FK_4982EC1787A5F842');
        $this->addSql('ALTER TABLE menu_evento DROP FOREIGN KEY FK_94C153DD87A5F842');
        $this->addSql('ALTER TABLE pago DROP FOREIGN KEY FK_F4DF5F3EFFD5FBD3');
        $this->addSql('ALTER TABLE pago DROP FOREIGN KEY FK_F4DF5F3EEC7D893C');
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
        $this->addSql('ALTER TABLE seleccion_participante_evento_linea DROP FOREIGN KEY FK_E5EF0816CCD7E912');
        $this->addSql('ALTER TABLE seleccion_participante_evento_linea DROP FOREIGN KEY FK_E5EF0816E51FA6DF');
        $this->addSql('ALTER TABLE usuario DROP FOREIGN KEY FK_2265B05D6CA204EF');
        $this->addSql('ALTER TABLE usuario DROP FOREIGN KEY FK_2265B05D6566B364');
        $this->addSql('ALTER TABLE usuario DROP FOREIGN KEY FK_2265B05D8892BEA7');
        $this->addSql('DROP TABLE censo_entrada');
        $this->addSql('DROP TABLE entidad');
        $this->addSql('DROP TABLE entidad_admins');
        $this->addSql('DROP TABLE evento');
        $this->addSql('DROP TABLE inscripcion');
        $this->addSql('DROP TABLE inscripcion_linea');
        $this->addSql('DROP TABLE invitado');
        $this->addSql('DROP TABLE menu_evento');
        $this->addSql('DROP TABLE pago');
        $this->addSql('DROP TABLE relacion_usuario');
        $this->addSql('DROP TABLE seleccion_participante_evento');
        $this->addSql('DROP TABLE seleccion_participante_evento_linea');
        $this->addSql('DROP TABLE usuario');
    }
}
