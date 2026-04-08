<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'No-op migration; menu removal now uses DELETE on inscripcion_linea';
    }

    public function up(Schema $schema): void
    {
        // no-op
    }

    public function down(Schema $schema): void
    {
        // no-op
    }
}

