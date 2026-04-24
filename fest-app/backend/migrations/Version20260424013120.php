<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260424013120 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE actividad_evento ADD precio_infantil_externo NUMERIC(8, 2) DEFAULT NULL');
        $this->addSql('DROP INDEX IDX_AUDIT_ENTITY ON audit');
        $this->addSql('DROP INDEX IDX_AUDIT_CREATED_AT ON audit');
        $this->addSql('ALTER TABLE audit CHANGE id id CHAR(36) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE actividad_evento DROP precio_infantil_externo');
        $this->addSql('ALTER TABLE audit CHANGE id id VARCHAR(36) NOT NULL');
        $this->addSql('CREATE INDEX IDX_AUDIT_ENTITY ON audit (entity_type, entity_id)');
        $this->addSql('CREATE INDEX IDX_AUDIT_CREATED_AT ON audit (created_at)');
    }
}
