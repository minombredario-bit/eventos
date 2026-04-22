<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add audit table to record actions (deletions and others) for multiple entity types.';
    }

    public function up(Schema $schema): void
    {
        // Create a generic audit table used for recording actions (delete/insert/update) across entity types.
        // Added `reason` column and indexes on (entity_type, entity_id) and created_at for faster lookups.
        $this->addSql('CREATE TABLE audit (id VARCHAR(36) NOT NULL, entity_type VARCHAR(100) NOT NULL, entity_id VARCHAR(255) NOT NULL, actor_id VARCHAR(255) DEFAULT NULL, action VARCHAR(50) NOT NULL, reason VARCHAR(255) DEFAULT NULL, changes JSON DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE INDEX IDX_AUDIT_ENTITY ON audit (entity_type, entity_id)');
        $this->addSql('CREATE INDEX IDX_AUDIT_CREATED_AT ON audit (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit');
    }
}

