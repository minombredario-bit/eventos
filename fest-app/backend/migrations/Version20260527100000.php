<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260527100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add password_reset_token table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE password_reset_token (
                id          INT AUTO_INCREMENT NOT NULL,
                token       VARCHAR(128) NOT NULL,
                email       VARCHAR(180) NOT NULL,
                expires_at  DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                used_at     DATETIME NULL COMMENT '(DC2Type:datetime_immutable)',
                created_at  DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',

                UNIQUE INDEX UNIQ_PASSWORD_RESET_TOKEN (token),
                INDEX IDX_PASSWORD_RESET_EMAIL (email),

                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE password_reset_token');
    }
}
