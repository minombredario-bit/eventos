<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260326021930 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE invitado (id CHAR(36) NOT NULL, nombre VARCHAR(255) NOT NULL, apellidos VARCHAR(255) NOT NULL, nombre_completo VARCHAR(255) NOT NULL, tipo_persona VARCHAR(50) NOT NULL, observaciones LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, creado_por_id CHAR(36) NOT NULL, evento_id CHAR(36) NOT NULL, INDEX IDX_4982EC17FE35D8C4 (creado_por_id), INDEX IDX_4982EC1787A5F842 (evento_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE relacion_usuario (id CHAR(36) NOT NULL, tipo_relacion VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, usuario_origen_id CHAR(36) NOT NULL, usuario_destino_id CHAR(36) NOT NULL, INDEX IDX_439EA65F1A6974DF (usuario_origen_id), INDEX IDX_439EA65F17064CB7 (usuario_destino_id), UNIQUE INDEX unique_relacion (usuario_origen_id, usuario_destino_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE seleccion_participantes_evento (id CHAR(36) NOT NULL, participantes JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, usuario_id CHAR(36) NOT NULL, evento_id CHAR(36) NOT NULL, INDEX IDX_C130BC4FDB38439E (usuario_id), INDEX IDX_C130BC4F87A5F842 (evento_id), UNIQUE INDEX uniq_seleccion_usuario_evento (usuario_id, evento_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE invitado ADD CONSTRAINT FK_4982EC17FE35D8C4 FOREIGN KEY (creado_por_id) REFERENCES usuario (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE invitado ADD CONSTRAINT FK_4982EC1787A5F842 FOREIGN KEY (evento_id) REFERENCES evento (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE relacion_usuario ADD CONSTRAINT FK_439EA65F1A6974DF FOREIGN KEY (usuario_origen_id) REFERENCES usuario (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE relacion_usuario ADD CONSTRAINT FK_439EA65F17064CB7 FOREIGN KEY (usuario_destino_id) REFERENCES usuario (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE seleccion_participantes_evento ADD CONSTRAINT FK_C130BC4FDB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE seleccion_participantes_evento ADD CONSTRAINT FK_C130BC4F87A5F842 FOREIGN KEY (evento_id) REFERENCES evento (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE persona_familiar DROP FOREIGN KEY `FK_6AE7DE1F4D70F53B`');
        $this->addSql('ALTER TABLE persona_familiar DROP FOREIGN KEY `FK_6AE7DE1F8892BEA7`');
        $this->addSql('DROP TABLE persona_familiar');
        $this->addSql('DROP INDEX unique_usuario_evento ON inscripcion');
        $this->addSql('ALTER TABLE inscripcion_linea DROP FOREIGN KEY `FK_2B833302F5F88DB9`');
        $this->addSql('DROP INDEX IDX_2B833302F5F88DB9 ON inscripcion_linea');
        $this->addSql('ALTER TABLE inscripcion_linea ADD invitado_id CHAR(36) DEFAULT NULL, DROP persona_id, CHANGE tipo_relacion_economica_snapshot tipo_relacion_economica_snapshot VARCHAR(50) DEFAULT NULL, CHANGE estado_validacion_snapshot estado_validacion_snapshot VARCHAR(50) DEFAULT NULL, CHANGE franja_comida_snapshot franja_comida_snapshot VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE inscripcion_linea ADD CONSTRAINT FK_2B8333028E552E60 FOREIGN KEY (invitado_id) REFERENCES invitado (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX IDX_2B8333028E552E60 ON inscripcion_linea (invitado_id)');
        $this->addSql('ALTER TABLE menu_evento CHANGE franja_comida franja_comida VARCHAR(50) NOT NULL, CHANGE compatibilidad_persona compatibilidad_persona VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE usuario ADD nombre_completo VARCHAR(255) NOT NULL, ADD fecha_nacimiento DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE persona_familiar (id CHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, nombre VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, apellidos VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, parentesco VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, tipo_persona VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, fecha_nacimiento DATE DEFAULT NULL, observaciones LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, activa TINYINT NOT NULL, tipo_relacion_economica VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, estado_validacion VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, fecha_validacion DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, usuario_principal_id CHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, validado_por_id CHAR(36) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, INDEX IDX_6AE7DE1F8892BEA7 (validado_por_id), INDEX IDX_6AE7DE1F4D70F53B (usuario_principal_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE persona_familiar ADD CONSTRAINT `FK_6AE7DE1F4D70F53B` FOREIGN KEY (usuario_principal_id) REFERENCES usuario (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE persona_familiar ADD CONSTRAINT `FK_6AE7DE1F8892BEA7` FOREIGN KEY (validado_por_id) REFERENCES usuario (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE invitado DROP FOREIGN KEY FK_4982EC17FE35D8C4');
        $this->addSql('ALTER TABLE invitado DROP FOREIGN KEY FK_4982EC1787A5F842');
        $this->addSql('ALTER TABLE relacion_usuario DROP FOREIGN KEY FK_439EA65F1A6974DF');
        $this->addSql('ALTER TABLE relacion_usuario DROP FOREIGN KEY FK_439EA65F17064CB7');
        $this->addSql('ALTER TABLE seleccion_participantes_evento DROP FOREIGN KEY FK_C130BC4FDB38439E');
        $this->addSql('ALTER TABLE seleccion_participantes_evento DROP FOREIGN KEY FK_C130BC4F87A5F842');
        $this->addSql('DROP TABLE invitado');
        $this->addSql('DROP TABLE relacion_usuario');
        $this->addSql('DROP TABLE seleccion_participantes_evento');
        $this->addSql('CREATE UNIQUE INDEX unique_usuario_evento ON inscripcion (usuario_id, evento_id)');
        $this->addSql('ALTER TABLE inscripcion_linea DROP FOREIGN KEY FK_2B8333028E552E60');
        $this->addSql('DROP INDEX IDX_2B8333028E552E60 ON inscripcion_linea');
        $this->addSql('ALTER TABLE inscripcion_linea ADD persona_id CHAR(36) NOT NULL, DROP invitado_id, CHANGE tipo_relacion_economica_snapshot tipo_relacion_economica_snapshot VARCHAR(50) NOT NULL, CHANGE estado_validacion_snapshot estado_validacion_snapshot VARCHAR(50) NOT NULL, CHANGE franja_comida_snapshot franja_comida_snapshot VARCHAR(50) DEFAULT \'comida\' NOT NULL');
        $this->addSql('ALTER TABLE inscripcion_linea ADD CONSTRAINT `FK_2B833302F5F88DB9` FOREIGN KEY (persona_id) REFERENCES persona_familiar (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_2B833302F5F88DB9 ON inscripcion_linea (persona_id)');
        $this->addSql('ALTER TABLE menu_evento CHANGE franja_comida franja_comida VARCHAR(50) DEFAULT \'comida\' NOT NULL, CHANGE compatibilidad_persona compatibilidad_persona VARCHAR(50) DEFAULT \'ambos\' NOT NULL');
        $this->addSql('ALTER TABLE usuario DROP nombre_completo, DROP fecha_nacimiento');
    }
}
