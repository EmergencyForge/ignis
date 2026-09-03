<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Zugangs-Codes, mit denen Krankenhäuser die Verfügbarkeit ihrer
 * Abteilungen selbst pflegen können — genau ein Code pro POI.
 */
class CreateIntraEdiviHospitalAccessCodes21012026 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_edivi_hospital_access_codes')) {
            return;
        }

        $this->table('intra_edivi_hospital_access_codes', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('poi_id',     'integer',   ['null' => false])
            ->addColumn('code',       'string',    ['limit' => 255, 'null' => false, 'comment' => 'Hashed access code/password'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['poi_id'], ['unique' => true, 'name' => 'unique_poi_code'])
            ->addIndex(['poi_id'], ['name' => 'idx_poi_id'])
            ->addForeignKey('poi_id', 'intra_edivi_pois', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
