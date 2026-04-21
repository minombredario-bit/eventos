<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make event inscription window dates nullable for informational events.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evento CHANGE fecha_inicio_inscripcion fecha_inicio_inscripcion DATETIME DEFAULT NULL, CHANGE fecha_fin_inscripcion fecha_fin_inscripcion DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evento CHANGE fecha_inicio_inscripcion fecha_inicio_inscripcion DATETIME NOT NULL, CHANGE fecha_fin_inscripcion fecha_fin_inscripcion DATETIME NOT NULL');
    }
}
