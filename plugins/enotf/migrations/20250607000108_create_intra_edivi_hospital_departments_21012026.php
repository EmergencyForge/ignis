<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Krankenhaus-Abteilungen pro POI (z. B. ZNA/INA, Schockraum,
 * Intensivstation). Grundlage für die Verfügbarkeitsanzeige der Kliniken.
 */
class CreateIntraEdiviHospitalDepartments21012026 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_edivi_hospital_departments')) {
            return;
        }

        $this->table('intra_edivi_hospital_departments', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('poi_id',     'integer',   ['null' => false])
            ->addColumn('name',       'string',    ['limit' => 255, 'null' => false, 'comment' => 'Department name (e.g., ZNA/INA, Schockraum, Intensivstation)'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['poi_id'], ['name' => 'idx_poi_id'])
            ->addForeignKey('poi_id', 'intra_edivi_pois', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
