<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260601000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique constraint to inscripcion (usuario_id + evento_id) to prevent duplicates and remove existing duplicates';
    }

    public function up(Schema $schema): void
    {
        // Primero, eliminar las líneas de inscripción de los registros duplicados que vamos a borrar
        // Nos quedamos con la inscripción más antigua para cada usuario+evento
        $this->addSql(<<<'SQL'
            DELETE FROM inscripcion_linea
            WHERE inscripcion_id IN (
                SELECT id FROM inscripcion
                WHERE id NOT IN (
                    SELECT MIN(i.id)
                    FROM inscripcion i
                    GROUP BY i.usuario_id, i.evento_id
                )
            )
        SQL);

        // Eliminar los pagos de inscripciones duplicadas
        $this->addSql(<<<'SQL'
            DELETE FROM pago
            WHERE inscripcion_id IN (
                SELECT id FROM inscripcion
                WHERE id NOT IN (
                    SELECT MIN(i.id)
                    FROM inscripcion i
                    GROUP BY i.usuario_id, i.evento_id
                )
            )
        SQL);

        // Finalmente, eliminar las inscripciones duplicadas, mantiendo la más antigua
        $this->addSql(<<<'SQL'
            DELETE FROM inscripcion
            WHERE id NOT IN (
                SELECT MIN(i.id)
                FROM inscripcion i
                GROUP BY i.usuario_id, i.evento_id
            )
        SQL);

        // Ahora añadir la constraint única
        $this->addSql('ALTER TABLE inscripcion ADD CONSTRAINT uniq_inscripcion_usuario_evento UNIQUE (usuario_id, evento_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inscripcion DROP CONSTRAINT uniq_inscripcion_usuario_evento');
    }
}

