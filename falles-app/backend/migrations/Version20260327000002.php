<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260327000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add menu slot/person compatibility and line slot snapshot';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE menu_evento ADD franja_comida VARCHAR(50) NOT NULL DEFAULT 'comida', ADD compatibilidad_persona VARCHAR(50) NOT NULL DEFAULT 'ambos'");
        $this->addSql("ALTER TABLE inscripcion_linea ADD franja_comida_snapshot VARCHAR(50) NOT NULL DEFAULT 'comida'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu_evento DROP franja_comida, DROP compatibilidad_persona');
        $this->addSql('ALTER TABLE inscripcion_linea DROP franja_comida_snapshot');
    }
}
