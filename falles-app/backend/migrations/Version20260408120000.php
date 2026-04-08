<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove CensoEntrada model, add entidad.censado and usuario.antiguedad/forma_pago_preferida, and drop usuario.censo_entrada_ref_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entidad ADD censado TINYINT(1) NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE usuario ADD antiguedad SMALLINT DEFAULT NULL, ADD forma_pago_preferida VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE usuario DROP FOREIGN KEY FK_2265B05D6566B364');
        $this->addSql('DROP INDEX IDX_2265B05D6566B364 ON usuario');
        $this->addSql('ALTER TABLE usuario DROP censo_entrada_ref_id');
        $this->addSql('DROP TABLE IF EXISTS censo_entrada');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE censo_entrada (id CHAR(36) NOT NULL, nombre VARCHAR(100) NOT NULL, apellidos VARCHAR(150) NOT NULL, email VARCHAR(180) DEFAULT NULL, dni VARCHAR(20) DEFAULT NULL, parentesco VARCHAR(50) NOT NULL, tipo_persona VARCHAR(50) NOT NULL, tipo_relacion_economica VARCHAR(50) NOT NULL, temporada VARCHAR(10) NOT NULL, procesado TINYINT NOT NULL, created_at DATETIME NOT NULL, entidad_id CHAR(36) NOT NULL, usuario_vinculado_id CHAR(36) DEFAULT NULL, INDEX IDX_4899BABA6CA204EF (entidad_id), INDEX IDX_4899BABAC63A09D1 (usuario_vinculado_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE censo_entrada ADD CONSTRAINT FK_4899BABA6CA204EF FOREIGN KEY (entidad_id) REFERENCES entidad (id)');
        $this->addSql('ALTER TABLE censo_entrada ADD CONSTRAINT FK_4899BABAC63A09D1 FOREIGN KEY (usuario_vinculado_id) REFERENCES usuario (id)');
        $this->addSql('ALTER TABLE usuario ADD censo_entrada_ref_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE usuario ADD CONSTRAINT FK_2265B05D6566B364 FOREIGN KEY (censo_entrada_ref_id) REFERENCES usuario (id)');
        $this->addSql('CREATE INDEX IDX_2265B05D6566B364 ON usuario (censo_entrada_ref_id)');
        $this->addSql('ALTER TABLE usuario DROP antiguedad, DROP forma_pago_preferida');
        $this->addSql('ALTER TABLE entidad DROP censado');
    }
}

