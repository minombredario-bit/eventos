<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260326021930 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Idempotent schema update with seleccion_participantes_evento dedupe and unique index enforcement';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            'This migration supports only MySQL.',
        );

        $this->addSql('CREATE TABLE IF NOT EXISTS invitado (id CHAR(36) NOT NULL, nombre VARCHAR(255) NOT NULL, apellidos VARCHAR(255) NOT NULL, nombre_completo VARCHAR(255) NOT NULL, tipo_persona VARCHAR(50) NOT NULL, observaciones LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, creado_por_id CHAR(36) NOT NULL, evento_id CHAR(36) NOT NULL, INDEX IDX_4982EC17FE35D8C4 (creado_por_id), INDEX IDX_4982EC1787A5F842 (evento_id), INDEX IDX_4982EC17C76F1F52 (deleted_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE IF NOT EXISTS relacion_usuario (id CHAR(36) NOT NULL, tipo_relacion VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, usuario_origen_id CHAR(36) NOT NULL, usuario_destino_id CHAR(36) NOT NULL, INDEX IDX_439EA65F1A6974DF (usuario_origen_id), INDEX IDX_439EA65F17064CB7 (usuario_destino_id), UNIQUE INDEX unique_relacion (usuario_origen_id, usuario_destino_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE IF NOT EXISTS seleccion_participantes_evento (id CHAR(36) NOT NULL, participantes JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, usuario_id CHAR(36) NOT NULL, evento_id CHAR(36) NOT NULL, INDEX IDX_C130BC4FDB38439E (usuario_id), INDEX IDX_C130BC4F87A5F842 (evento_id), UNIQUE INDEX uniq_seleccion_usuario_evento (usuario_id, evento_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        $this->ensureForeignKey('invitado', 'FK_4982EC17FE35D8C4', 'ALTER TABLE invitado ADD CONSTRAINT FK_4982EC17FE35D8C4 FOREIGN KEY (creado_por_id) REFERENCES usuario (id) ON DELETE CASCADE');
        $this->ensureForeignKey('invitado', 'FK_4982EC1787A5F842', 'ALTER TABLE invitado ADD CONSTRAINT FK_4982EC1787A5F842 FOREIGN KEY (evento_id) REFERENCES evento (id) ON DELETE CASCADE');
        $this->ensureForeignKey('relacion_usuario', 'FK_439EA65F1A6974DF', 'ALTER TABLE relacion_usuario ADD CONSTRAINT FK_439EA65F1A6974DF FOREIGN KEY (usuario_origen_id) REFERENCES usuario (id) ON DELETE CASCADE');
        $this->ensureForeignKey('relacion_usuario', 'FK_439EA65F17064CB7', 'ALTER TABLE relacion_usuario ADD CONSTRAINT FK_439EA65F17064CB7 FOREIGN KEY (usuario_destino_id) REFERENCES usuario (id) ON DELETE CASCADE');
        $this->ensureForeignKey('seleccion_participantes_evento', 'FK_C130BC4FDB38439E', 'ALTER TABLE seleccion_participantes_evento ADD CONSTRAINT FK_C130BC4FDB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE');
        $this->ensureForeignKey('seleccion_participantes_evento', 'FK_C130BC4F87A5F842', 'ALTER TABLE seleccion_participantes_evento ADD CONSTRAINT FK_C130BC4F87A5F842 FOREIGN KEY (evento_id) REFERENCES evento (id) ON DELETE CASCADE');

        if ($this->tableExists('invitado')) {
            if (!$this->columnExists('invitado', 'deleted_at')) {
                $this->addSql('ALTER TABLE invitado ADD deleted_at DATETIME DEFAULT NULL');
            }

            $this->ensureIndex('invitado', 'IDX_4982EC17C76F1F52', 'CREATE INDEX IDX_4982EC17C76F1F52 ON invitado (deleted_at)');
        }

        if ($this->tableExists('persona_familiar')) {
            $this->addSql('DROP TABLE IF EXISTS persona_familiar');
        }

        $this->dropIndexIfExists('inscripcion', 'unique_usuario_evento');

        if ($this->tableExists('inscripcion_linea')) {
            if ($this->foreignKeyExists('inscripcion_linea', 'FK_2B833302F5F88DB9')) {
                $this->addSql('ALTER TABLE inscripcion_linea DROP FOREIGN KEY `FK_2B833302F5F88DB9`');
            }

            $this->dropIndexIfExists('inscripcion_linea', 'IDX_2B833302F5F88DB9');

            if (!$this->columnExists('inscripcion_linea', 'invitado_id')) {
                $this->addSql('ALTER TABLE inscripcion_linea ADD invitado_id CHAR(36) DEFAULT NULL');
            }

            if ($this->columnExists('inscripcion_linea', 'persona_id')) {
                $this->addSql('ALTER TABLE inscripcion_linea DROP COLUMN persona_id');
            }

            if ($this->columnExists('inscripcion_linea', 'tipo_relacion_economica_snapshot')) {
                $this->addSql('ALTER TABLE inscripcion_linea MODIFY tipo_relacion_economica_snapshot VARCHAR(50) DEFAULT NULL');
            }

            if ($this->columnExists('inscripcion_linea', 'estado_validacion_snapshot')) {
                $this->addSql('ALTER TABLE inscripcion_linea MODIFY estado_validacion_snapshot VARCHAR(50) DEFAULT NULL');
            }

            if ($this->columnExists('inscripcion_linea', 'franja_comida_snapshot')) {
                $this->addSql('ALTER TABLE inscripcion_linea MODIFY franja_comida_snapshot VARCHAR(50) NOT NULL');
            }

            $this->ensureForeignKey('inscripcion_linea', 'FK_2B8333028E552E60', 'ALTER TABLE inscripcion_linea ADD CONSTRAINT FK_2B8333028E552E60 FOREIGN KEY (invitado_id) REFERENCES invitado (id) ON DELETE RESTRICT');
            $this->ensureIndex('inscripcion_linea', 'IDX_2B8333028E552E60', 'CREATE INDEX IDX_2B8333028E552E60 ON inscripcion_linea (invitado_id)');
        }

        if ($this->tableExists('menu_evento')) {
            if ($this->columnExists('menu_evento', 'franja_comida')) {
                $this->addSql('ALTER TABLE menu_evento MODIFY franja_comida VARCHAR(50) NOT NULL');
            }

            if ($this->columnExists('menu_evento', 'compatibilidad_persona')) {
                $this->addSql('ALTER TABLE menu_evento MODIFY compatibilidad_persona VARCHAR(50) NOT NULL');
            }
        }

        if ($this->tableExists('usuario')) {
            if (!$this->columnExists('usuario', 'nombre_completo')) {
                $this->addSql('ALTER TABLE usuario ADD nombre_completo VARCHAR(255) NOT NULL');
            }

            if (!$this->columnExists('usuario', 'fecha_nacimiento')) {
                $this->addSql('ALTER TABLE usuario ADD fecha_nacimiento DATE DEFAULT NULL');
            }
        }

        $this->sanitizeSeleccionParticipantesEvento();
    }

    private function sanitizeSeleccionParticipantesEvento(): void
    {
        if (!$this->tableExists('seleccion_participantes_evento')) {
            return;
        }

        $this->addSql(<<<'SQL'
DELETE target
FROM seleccion_participantes_evento target
INNER JOIN (
    SELECT id,
           ROW_NUMBER() OVER (
               PARTITION BY usuario_id, evento_id
               ORDER BY updated_at DESC, created_at DESC, id DESC
           ) AS rn
    FROM seleccion_participantes_evento
) ranked ON ranked.id = target.id
WHERE ranked.rn > 1
SQL);

        $existingUniqueIndex = $this->connection->fetchOne(<<<'SQL'
SELECT candidate.index_name
FROM (
    SELECT index_name
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'seleccion_participantes_evento'
    GROUP BY index_name
    HAVING MIN(non_unique) = 0
       AND COUNT(*) = 2
       AND SUM(CASE WHEN column_name = 'usuario_id' AND seq_in_index = 1 THEN 1 ELSE 0 END) = 1
       AND SUM(CASE WHEN column_name = 'evento_id' AND seq_in_index = 2 THEN 1 ELSE 0 END) = 1
) candidate
ORDER BY candidate.index_name
LIMIT 1
SQL);

        if (false === $existingUniqueIndex || null === $existingUniqueIndex) {
            if ($this->indexExists('seleccion_participantes_evento', 'uniq_seleccion_usuario_evento')) {
                $this->addSql('DROP INDEX uniq_seleccion_usuario_evento ON seleccion_participantes_evento');
            }

            $this->addSql('ALTER TABLE seleccion_participantes_evento ADD UNIQUE INDEX uniq_seleccion_usuario_evento (usuario_id, evento_id)');

            return;
        }

        if ('uniq_seleccion_usuario_evento' !== $existingUniqueIndex) {
            if ($this->indexExists('seleccion_participantes_evento', 'uniq_seleccion_usuario_evento')) {
                $this->addSql('DROP INDEX uniq_seleccion_usuario_evento ON seleccion_participantes_evento');
            }

            $this->addSql(sprintf('ALTER TABLE seleccion_participantes_evento RENAME INDEX `%s` TO `uniq_seleccion_usuario_evento`', $existingUniqueIndex));
        }
    }

    private function ensureForeignKey(string $tableName, string $constraintName, string $sql): void
    {
        if (!$this->tableExists($tableName)) {
            return;
        }

        if (!$this->foreignKeyExists($tableName, $constraintName)) {
            $this->addSql($sql);
        }
    }

    private function ensureIndex(string $tableName, string $indexName, string $sql): void
    {
        if (!$this->tableExists($tableName)) {
            return;
        }

        if (!$this->indexExists($tableName, $indexName)) {
            $this->addSql($sql);
        }
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if ($this->tableExists($tableName) && $this->indexExists($tableName, $indexName)) {
            $this->addSql(sprintf('DROP INDEX %s ON %s', $indexName, $tableName));
        }
    }

    private function tableExists(string $tableName): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table',
            ['table' => $tableName],
        ) > 0;
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column',
            ['table' => $tableName, 'column' => $columnName],
        ) > 0;
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index',
            ['table' => $tableName, 'index' => $indexName],
        ) > 0;
    }

    private function foreignKeyExists(string $tableName, string $constraintName): bool
    {
        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = :table AND constraint_name = :constraint AND constraint_type = 'FOREIGN KEY'",
            ['table' => $tableName, 'constraint' => $constraintName],
        ) > 0;
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE persona_familiar (id CHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, nombre VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, apellidos VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, parentesco VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, tipo_persona VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, fecha_nacimiento DATE DEFAULT NULL, observaciones LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, activa TINYINT NOT NULL, tipo_relacion_economica VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, estado_validacion VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, fecha_validacion DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, usuario_principal_id CHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, validado_por_id CHAR(36) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, INDEX IDX_6AE7DE1F8892BEA7 (validado_por_id), INDEX IDX_6AE7DE1F4D70F53B (usuario_principal_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE persona_familiar ADD CONSTRAINT `FK_6AE7DE1F4D70F53B` FOREIGN KEY (usuario_principal_id) REFERENCES usuario (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE persona_familiar ADD CONSTRAINT `FK_6AE7DE1F8892BEA7` FOREIGN KEY (validado_por_id) REFERENCES usuario (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE invitado DROP FOREIGN KEY FK_4982EC17FE35D8C4');
        $this->addSql('ALTER TABLE invitado DROP FOREIGN KEY FK_4982EC1787A5F842');
        $this->addSql('ALTER TABLE relacion_usuario DROP FOREIGN KEY FK_439EA65F1A6974DF');
        $this->addSql('ALTER TABLE relacion_usuario DROP FOREIGN KEY FK_439EA65F17064CB7');
        $this->addSql('ALTER TABLE seleccion_participantes_evento DROP FOREIGN KEY FK_C130BC4FDB38439E');
        $this->addSql('ALTER TABLE seleccion_participantes_evento DROP FOREIGN KEY FK_C130BC4F87A5F842');
        $this->addSql('DROP TABLE invitado');
        $this->addSql('DROP TABLE relacion_usuario');
        $this->addSql('DROP TABLE seleccion_participantes_evento');
        $this->addSql('CREATE UNIQUE INDEX unique_usuario_evento ON inscripcion (usuario_id, evento_id)');
        $this->addSql('ALTER TABLE inscripcion_linea DROP FOREIGN KEY FK_2B8333028E552E60');
        $this->addSql('DROP INDEX IDX_2B8333028E552E60 ON inscripcion_linea');
        $this->addSql('ALTER TABLE inscripcion_linea ADD persona_id CHAR(36) NOT NULL, DROP invitado_id, CHANGE tipo_relacion_economica_snapshot tipo_relacion_economica_snapshot VARCHAR(50) NOT NULL, CHANGE estado_validacion_snapshot estado_validacion_snapshot VARCHAR(50) NOT NULL, CHANGE franja_comida_snapshot franja_comida_snapshot VARCHAR(50) DEFAULT \'comida\' NOT NULL');
        $this->addSql('ALTER TABLE inscripcion_linea ADD CONSTRAINT `FK_2B833302F5F88DB9` FOREIGN KEY (persona_id) REFERENCES persona_familiar (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_2B833302F5F88DB9 ON inscripcion_linea (persona_id)');
        $this->addSql('ALTER TABLE menu_evento CHANGE franja_comida franja_comida VARCHAR(50) DEFAULT \'comida\' NOT NULL, CHANGE compatibilidad_persona compatibilidad_persona VARCHAR(50) DEFAULT \'ambos\' NOT NULL');
        $this->addSql('ALTER TABLE usuario DROP nombre_completo, DROP fecha_nacimiento');
    }
}
