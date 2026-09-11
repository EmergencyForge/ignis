<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Federation-Verknüpfungen zu anderen intraRP-Instanzen: pro Link die
 * API-Keys in beide Richtungen sowie Consume-/Provide-Flags für Personal,
 * eNOTF-Protokolle und Feuerwehr-Einsätze, dazu Sync-Intervall und -Status.
 */
class CreateIntraFederationLinks25032026 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_federation_links')) {
            return;
        }

        $this->table('intra_federation_links', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('instance_id', 'string', ['limit' => 36, 'comment' => 'UUID of remote instance', 'null' => false])
            ->addColumn('instance_name', 'string', ['limit' => 100, 'comment' => 'Display name of remote instance', 'null' => false])
            ->addColumn('instance_url', 'string', ['limit' => 255, 'comment' => 'Base URL of remote instance', 'null' => false])
            ->addColumn('api_key_outgoing', 'string', ['limit' => 64, 'comment' => 'Key we send when fetching from them', 'null' => false])
            ->addColumn('api_key_incoming', 'string', ['limit' => 64, 'comment' => 'Key they must send to us', 'null' => false])
            ->addColumn('consume_personnel', 'boolean', ['default' => 0, 'comment' => 'Pull their personnel', 'null' => false])
            ->addColumn('consume_enotf', 'boolean', ['default' => 0, 'comment' => 'Pull their eNOTF protocols', 'null' => false])
            ->addColumn('consume_fire', 'boolean', ['default' => 0, 'comment' => 'Pull their fire incidents', 'null' => false])
            ->addColumn('provide_personnel', 'boolean', ['default' => 0, 'comment' => 'Expose our personnel to them', 'null' => false])
            ->addColumn('provide_enotf', 'boolean', ['default' => 0, 'comment' => 'Expose our eNOTF protocols to them', 'null' => false])
            ->addColumn('provide_fire', 'boolean', ['default' => 0, 'comment' => 'Expose our fire incidents to them', 'null' => false])
            ->addColumn('sync_interval_minutes', 'integer', ['default' => 15, 'null' => false])
            ->addColumn('last_sync_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('last_sync_status', 'enum', [
                'values'  => ['success', 'error', 'pending'],
                'default' => 'pending',
                'null'    => false,
            ])
            ->addColumn('last_sync_error', 'text', ['null' => true, 'default' => null])
            ->addColumn('is_active', 'boolean', ['default' => 1, 'null' => false])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addColumn('updated_at', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP',
                'update'  => 'CURRENT_TIMESTAMP',
                'null'    => false,
            ])
            ->addIndex(['instance_id'], ['unique' => true, 'name' => 'idx_instance_id'])
            ->create();
    }
}
