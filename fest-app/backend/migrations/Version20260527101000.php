<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260527101000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add passkey_credential table for WebAuthn/FIDO2 passkeys';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE passkey_credential (
                id            CHAR(36)     NOT NULL,
                usuario_id    CHAR(36)     NOT NULL,
                credential_id LONGTEXT     NOT NULL,
                public_key    LONGTEXT     NOT NULL,
                sign_count    INT          NOT NULL DEFAULT 0,
                device_name   VARCHAR(100) NOT NULL DEFAULT 'Dispositivo',
                created_at    DATETIME     NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                last_used_at  DATETIME     NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX UNIQ_PASSKEY_CREDENTIAL_ID (credential_id(768)),
                INDEX IDX_PASSKEY_USUARIO (usuario_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_PASSKEY_USUARIO
                    FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE passkey_credential');
    }
}
