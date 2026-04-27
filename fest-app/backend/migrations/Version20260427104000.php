<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260427104000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create EntidadCargo rows only for Tipo Falla entidades and allowed CargoMaster codes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO entidad_cargo (id, entidad_id, cargo_master_id, nombre, `orden`, activo)
            SELECT
              UUID(),
              e.id,
              cm.id,
              NULL,
              NULL,
              1
            FROM entidad e
            INNER JOIN tipo_entidad te ON te.id = e.tipo_entidad_id
            CROSS JOIN cargo_master cm
            LEFT JOIN entidad_cargo ec
              ON ec.entidad_id = e.id
             AND ec.cargo_master_id = cm.id
            WHERE ec.id IS NULL
              AND te.codigo = 'FALLA'
              AND cm.codigo IN (
                'DELEGADO_FESTEJOS',
                'PRESIDENTE',
                'PRESIDENTE_INFANTIL',
                'VICESECRETARIO',
                'DELEGADO_PROTOCOLO',
                'FALLERA_MAYOR_INFANTIL',
                'VICEPRESIDENTE_1',
                'TESORERO',
                'VICEPRESIDENTE_2',
                'DELEGADO_CULTURA',
                'FALLERA_MAYOR',
                'DELEGADO_INFANTILES',
                'SECRETARIO',
                'ABANDERADO_INFANTIL'
              );
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE ec
            FROM entidad_cargo ec
            INNER JOIN entidad e ON e.id = ec.entidad_id
            INNER JOIN tipo_entidad te ON te.id = e.tipo_entidad_id
            INNER JOIN cargo_master cm ON cm.id = ec.cargo_master_id
            WHERE te.codigo = 'FALLA'
              AND cm.codigo IN (
                'DELEGADO_FESTEJOS',
                'PRESIDENTE',
                'PRESIDENTE_INFANTIL',
                'VICESECRETARIO',
                'DELEGADO_PROTOCOLO',
                'FALLERA_MAYOR_INFANTIL',
                'VICEPRESIDENTE_1',
                'TESORERO',
                'VICEPRESIDENTE_2',
                'DELEGADO_CULTURA',
                'FALLERA_MAYOR',
                'DELEGADO_INFANTILES',
                'SECRETARIO',
                'ABANDERADO_INFANTIL'
              );
            SQL);
    }
}
