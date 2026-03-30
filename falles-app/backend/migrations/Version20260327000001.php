<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260327000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique constraint on inscripcion (usuario_id, evento_id)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inscripcion ADD UNIQUE INDEX unique_usuario_evento (usuario_id, evento_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inscripcion DROP INDEX unique_usuario_evento');
    }
}
