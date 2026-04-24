<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add acepto_lopd and acepto_lopd_at columns to usuario';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE usuario ADD acepto_lopd TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE usuario ADD acepto_lopd_at DATETIME(0) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE usuario DROP acepto_lopd');
        $this->addSql('ALTER TABLE usuario DROP acepto_lopd_at');
    }
}

