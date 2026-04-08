<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add permite_invitados flag to evento';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evento ADD permite_invitados TINYINT(1) NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evento DROP permite_invitados');
    }
}

