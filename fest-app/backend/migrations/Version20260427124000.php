<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260427124000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set temporada_inicio_mes=3 and temporada_inicio_dia=1 for demo entidad (FALLA2024)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE entidad ADD temporada_inicio_mes SMALLINT DEFAULT 1 NOT NULL");
        $this->addSql("ALTER TABLE entidad ADD temporada_inicio_dia SMALLINT DEFAULT 1 NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entidad DROP temporada_inicio_mes');
        $this->addSql('ALTER TABLE entidad DROP temporada_inicio_dia');
    }
}

