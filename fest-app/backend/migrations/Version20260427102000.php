<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260427102000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Insert official cargos into cargo_master (presidente, fallera_mayor, vocal_apoyo, vocal_infantil_apoyo)';
    }

    public function up(Schema $schema): void
    {
        // Insert a small catalog of official cargos (managed by superadmin)
        $this->addSql("INSERT INTO cargo_master (id, nombre, codigo, descripcion, computa_como_directivo, es_representativo, es_infantil, infantil_especial, activo, orden_jerarquico, anios_computables) VALUES
            ('f47ac10b-58cc-4372-a567-0e02b2c3d479', 'Presidente', 'presidente', 'Presidente de la entidad', 1, 1, 0, 0, 1, 1, '1.00'),
            ('c9bf9e57-1685-4c89-bafb-ff5af830be8a', 'Fallera Mayor', 'fallera_mayor', 'Representante fallera principal', 0, 1, 0, 0, 1, 2, '1.00'),
            ('3fa85f64-5717-4562-b3fc-2c963f66afa6', 'Vocal de Apoyo', 'vocal_apoyo', 'Vocal de apoyo general', 0, 0, 0, 0, 1, 50, '1.00'),
            ('2b9c1d5e-4e6f-489a-9c3b-7a8d0e1f2b3c', 'Vocal Infantil de Apoyo', 'vocal_infantil_apoyo', 'Vocal de apoyo para área infantil', 0, 0, 1, 0, 1, 60, '1.00')
        ");
    }

    public function down(Schema $schema): void
    {
        // Remove the entries by codigo to safely rollback
        $this->addSql("DELETE FROM cargo_master WHERE codigo IN ('presidente', 'fallera_mayor', 'vocal_apoyo', 'vocal_infantil_apoyo')");
    }
}

