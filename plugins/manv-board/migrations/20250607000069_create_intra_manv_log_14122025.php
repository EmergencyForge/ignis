<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * MANV-Aktionslog: chronologisches Protokoll aller Aktionen je Lage
 * (Patient erstellt, Sichtung geändert, Transport zugewiesen, ...).
 */
class CreateIntraManvLog14122025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_manv_log')) {
            return;
        }

        $this->table('intra_manv_log', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
            'comment'   => 'MANV-Aktionslog',
        ])
            ->addColumn('manv_lage_id',  'integer',  ['null' => false])
            ->addColumn('timestamp',     'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('aktion',        'string',   ['limit' => 100, 'null' => false, 'comment' => 'z.B. patient_erstellt, sichtung_geaendert, transport_zugewiesen'])
            ->addColumn('beschreibung',  'text',     ['null' => true, 'default' => null])
            ->addColumn('benutzer_id',   'integer',  ['null' => true, 'default' => null])
            ->addColumn('benutzer_name', 'string',   ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('referenz_typ',  'string',   ['limit' => 50, 'null' => true, 'default' => null, 'comment' => 'patient, ressource, etc.'])
            ->addColumn('referenz_id',   'integer',  ['null' => true, 'default' => null])
            ->addIndex(['manv_lage_id'], ['name' => 'idx_manv_lage'])
            ->addIndex(['timestamp'],    ['name' => 'idx_timestamp'])
            ->addForeignKey('manv_lage_id', 'intra_manv_lagen', 'id', [
                'delete'     => 'CASCADE',
                'constraint' => 'fk_manv_log_lage',
            ])
            ->create();
    }
}
