<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Echtzeit-Verfügbarkeit der Krankenhaus-Abteilungen: pro Abteilung genau
 * ein Status (Grau=not_staffed, Grün=available, Gelb=partially_available,
 * Rot=full) samt Zeitstempel und Verursacher der letzten Änderung.
 */
class CreateIntraEdiviHospitalAvailability21012026 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_edivi_hospital_availability')) {
            return;
        }

        $this->table('intra_edivi_hospital_availability', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('department_id', 'integer',   ['null' => false])
            ->addColumn('status',        'enum',      ['values' => ['not_staffed', 'available', 'partially_available', 'full'], 'null' => false, 'default' => 'not_staffed', 'comment' => 'Grau=not_staffed, Grün=available, Gelb=partially_available, Rot=full'])
            ->addColumn('updated_at',    'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_by',    'string',    ['limit' => 255, 'null' => true, 'comment' => 'User or system that updated the status'])
            ->addIndex(['department_id'], ['unique' => true, 'name' => 'unique_department_status'])
            ->addIndex(['department_id'], ['name' => 'idx_department_id'])
            ->addIndex(['status'],        ['name' => 'idx_status'])
            ->addForeignKey('department_id', 'intra_edivi_hospital_departments', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
