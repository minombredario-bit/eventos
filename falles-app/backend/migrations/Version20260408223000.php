<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pagada flag to inscripcion_linea to lock paid lines individually';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inscripcion_linea ADD pagada TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inscripcion_linea DROP pagada');
    }
}

