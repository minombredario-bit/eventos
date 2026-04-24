<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424131000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add texto_lopd column to entidad';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entidad ADD texto_lopd LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entidad DROP texto_lopd');
    }
}

