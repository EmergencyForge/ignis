<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fahrzeug-Defektverwaltung: Meldungen mit Kategorie, Status-Workflow
 * (open → in_progress/deferred → resolved) und Einsatzfähigkeits-Flag,
 * plus Log-Tabelle für den Status-Verlauf (wer hat wann was geändert).
 */
class CreateIntraFahrzeugeDefects09032026 extends AbstractMigration
{
    public function change(): void
    {
        if (!$this->hasTable('intra_fahrzeuge_defects')) {
            $this->table('intra_fahrzeuge_defects', [
                'signed'    => true,
                'engine'    => 'InnoDB',
                'encoding'  => 'utf8mb4',
                'collation' => 'utf8mb4_general_ci',
            ])
                ->addColumn('vehicle_id', 'integer', ['null' => false])
                ->addColumn('title', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('category', 'string', ['limit' => 50, 'default' => 'sonstiges', 'null' => false])
                ->addColumn('vehicle_operable', 'boolean', ['default' => 1, 'null' => false])
                ->addColumn('status', 'enum', [
                    'values'  => ['open', 'in_progress', 'deferred', 'resolved'],
                    'default' => 'open',
                    'null'    => false,
                ])
                ->addColumn('reported_by', 'integer', ['null' => false])
                ->addColumn('assigned_to', 'integer', ['null' => true, 'default' => null])
                ->addColumn('resolved_by', 'integer', ['null' => true, 'default' => null])
                ->addColumn('resolved_at', 'datetime', ['null' => true, 'default' => null])
                ->addColumn('resolution_note', 'text', ['null' => true, 'default' => null])
                ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'timestamp', [
                    'null'    => true,
                    'default' => 'CURRENT_TIMESTAMP',
                    'update'  => 'CURRENT_TIMESTAMP',
                ])
                ->addIndex(['vehicle_id'], ['name' => 'idx_defects_vehicle'])
                ->addIndex(['status'], ['name' => 'idx_defects_status'])
                ->addIndex(['category'], ['name' => 'idx_defects_category'])
                ->addIndex(['vehicle_operable'], ['name' => 'idx_defects_operable'])
                ->addForeignKey('vehicle_id', 'intra_fahrzeuge', 'id', ['delete' => 'CASCADE'])
                ->create();
        }

        if (!$this->hasTable('intra_fahrzeuge_defect_log')) {
            $this->table('intra_fahrzeuge_defect_log', [
                'signed'    => true,
                'engine'    => 'InnoDB',
                'encoding'  => 'utf8mb4',
                'collation' => 'utf8mb4_general_ci',
            ])
                ->addColumn('defect_id', 'integer', ['null' => false])
                ->addColumn('user_id', 'integer', ['null' => false])
                ->addColumn('action', 'string', ['limit' => 50, 'null' => false])
                ->addColumn('details', 'text', ['null' => true, 'default' => null])
                ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['defect_id'], ['name' => 'idx_defect_log_defect'])
                ->addForeignKey('defect_id', 'intra_fahrzeuge_defects', 'id', ['delete' => 'CASCADE'])
                ->create();
        }
    }
}
