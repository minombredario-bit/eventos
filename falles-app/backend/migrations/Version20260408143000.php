<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cargo model, usuario_cargo pivot, cola_correo table, and password/antiguedadReal fields in usuario';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cargo (id CHAR(36) NOT NULL, entidad_id CHAR(36) NOT NULL, nombre VARCHAR(120) NOT NULL, descripcion LONGTEXT DEFAULT NULL, multiplicador NUMERIC(8, 2) NOT NULL, INDEX IDX_4B1B4D7A6CA204EF (entidad_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE usuario_cargo (usuario_id CHAR(36) NOT NULL, cargo_id CHAR(36) NOT NULL, INDEX IDX_57C55D14DB38439E (usuario_id), INDEX IDX_57C55D14A5CA7E0E (cargo_id), PRIMARY KEY(usuario_id, cargo_id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cola_correo (id CHAR(36) NOT NULL, entidad_id CHAR(36) DEFAULT NULL, usuario_id CHAR(36) DEFAULT NULL, destinatario VARCHAR(180) NOT NULL, asunto VARCHAR(255) NOT NULL, plantilla VARCHAR(120) NOT NULL, contexto JSON NOT NULL, estado VARCHAR(20) NOT NULL, intentos INT NOT NULL, ultimo_error LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, enviado_at DATETIME DEFAULT NULL, INDEX IDX_67D52C6A6CA204EF (entidad_id), INDEX IDX_67D52C6ADB38439E (usuario_id), INDEX IDX_67D52C6A9F5A440B (estado), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');

        $this->addSql('ALTER TABLE usuario ADD antiguedad_real SMALLINT DEFAULT NULL, ADD debe_cambiar_password TINYINT(1) NOT NULL DEFAULT 0, ADD password_actualizada_at DATETIME DEFAULT NULL');

        $this->addSql('ALTER TABLE cargo ADD CONSTRAINT FK_4B1B4D7A6CA204EF FOREIGN KEY (entidad_id) REFERENCES entidad (id)');
        $this->addSql('ALTER TABLE usuario_cargo ADD CONSTRAINT FK_57C55D14DB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE usuario_cargo ADD CONSTRAINT FK_57C55D14A5CA7E0E FOREIGN KEY (cargo_id) REFERENCES cargo (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cola_correo ADD CONSTRAINT FK_67D52C6A6CA204EF FOREIGN KEY (entidad_id) REFERENCES entidad (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cola_correo ADD CONSTRAINT FK_67D52C6ADB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE usuario_cargo DROP FOREIGN KEY FK_57C55D14DB38439E');
        $this->addSql('ALTER TABLE usuario_cargo DROP FOREIGN KEY FK_57C55D14A5CA7E0E');
        $this->addSql('ALTER TABLE cargo DROP FOREIGN KEY FK_4B1B4D7A6CA204EF');
        $this->addSql('ALTER TABLE cola_correo DROP FOREIGN KEY FK_67D52C6A6CA204EF');
        $this->addSql('ALTER TABLE cola_correo DROP FOREIGN KEY FK_67D52C6ADB38439E');

        $this->addSql('DROP TABLE usuario_cargo');
        $this->addSql('DROP TABLE cargo');
        $this->addSql('DROP TABLE cola_correo');

        $this->addSql('ALTER TABLE usuario DROP antiguedad_real, DROP debe_cambiar_password, DROP password_actualizada_at');
    }
}

