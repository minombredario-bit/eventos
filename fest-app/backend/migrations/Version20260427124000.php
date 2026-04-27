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
        // Update demo entity (identified by codigo_registro) to start season on 01/03
        $this->addSql("UPDATE entidad SET temporada_inicio_mes = 3, temporada_inicio_dia = 1 WHERE codigo_registro = 'FALLA2024'");
    }

    public function down(Schema $schema): void
    {
        // Revert to defaults (1/1) for the demo entity
        $this->addSql("UPDATE entidad SET temporada_inicio_mes = 1, temporada_inicio_dia = 1 WHERE codigo_registro = 'FALLA2024'");
    }
}

