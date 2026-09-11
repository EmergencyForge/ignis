<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Protokoll-Sharing zwischen Fahrzeugen: `intra_edivi_share_requests` hält
 * Anfragen, mit denen ein Fahrzeug sein Protokoll an ein anderes übergibt
 * (Status-Workflow pending/accepted/rejected/cancelled, inkl. Ergebnis —
 * gemergt oder als neues Protokoll übernommen).
 */
class CreateIntraEdiviShareRequests17122025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_edivi_share_requests')) {
            return;
        }

        $this->table('intra_edivi_share_requests', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('source_enr',         'string',    ['limit' => 255, 'null' => false, 'comment' => 'ENR des Quell-Protokolls'])
            ->addColumn('source_protocol_id', 'integer',   ['null' => false, 'comment' => 'ID des Quell-Protokolls'])
            ->addColumn('source_vehicle',     'string',    ['limit' => 255, 'null' => false, 'comment' => 'Fahrzeug das teilt'])
            ->addColumn('target_vehicle',     'string',    ['limit' => 255, 'null' => false, 'comment' => 'Fahrzeug das empfängt'])
            ->addColumn('status',             'enum',      ['values' => ['pending', 'accepted', 'rejected', 'cancelled'], 'null' => false, 'default' => 'pending'])
            ->addColumn('created_at',         'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at',         'timestamp', ['null' => true, 'default' => null, 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('response_at',        'timestamp', ['null' => true, 'default' => null])
            ->addColumn('response_by',        'string',    ['limit' => 255, 'null' => true, 'default' => null, 'comment' => 'Name der Person die geantwortet hat'])
            ->addColumn('action_taken',       'enum',      ['values' => ['merged', 'new_protocol'], 'null' => true, 'default' => null, 'comment' => 'Aktion die durchgeführt wurde'])
            ->addColumn('new_enr',            'string',    ['limit' => 255, 'null' => true, 'default' => null, 'comment' => 'Neue ENR falls neues Protokoll erstellt'])
            ->addIndex(['target_vehicle', 'status'], ['name' => 'idx_target_vehicle'])
            ->addIndex(['source_protocol_id'],       ['name' => 'idx_source_protocol'])
            ->addIndex(['created_at'],               ['name' => 'idx_created_at'])
            ->create();
    }
}
