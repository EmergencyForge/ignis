<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Lokale Caches für federierte Daten von Remote-Instanzen (Personal,
 * eNOTF-Protokolle, Feuerwehr-Einsätze) plus Sync-Log pro Verknüpfung.
 * Eindeutigkeit jeweils über (source_instance_id, remote_id).
 */
class CreateIntraFederationCache25032026 extends AbstractMigration
{
    public function change(): void
    {
        $tableOptions = [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ];

        if (!$this->hasTable('intra_federation_cache_personnel')) {
            $this->table('intra_federation_cache_personnel', $tableOptions)
                ->addColumn('source_instance_id', 'string', ['limit' => 36, 'null' => false])
                ->addColumn('remote_id', 'integer', ['null' => false])
                ->addColumn('fullname', 'string', ['limit' => 100, 'null' => true, 'default' => null])
                ->addColumn('dienstnr', 'string', ['limit' => 20, 'null' => true, 'default' => null])
                ->addColumn('dienstgrad_name', 'string', ['limit' => 255, 'null' => true, 'default' => null])
                ->addColumn('dienstgrad_badge', 'string', ['limit' => 255, 'null' => true, 'default' => null])
                ->addColumn('quali_rd', 'string', ['limit' => 255, 'null' => true, 'default' => null])
                ->addColumn('quali_fw', 'string', ['limit' => 255, 'null' => true, 'default' => null])
                ->addColumn('quali_fd', 'string', ['limit' => 255, 'null' => true, 'default' => null])
                ->addColumn('cached_data', 'json', [
                    'null'    => true,
                    'default' => null,
                    'comment' => 'Full record as fallback',
                ])
                ->addColumn('cached_at', 'datetime', ['null' => false])
                ->addIndex(['source_instance_id', 'remote_id'], ['unique' => true, 'name' => 'idx_source_remote'])
                ->addIndex(['source_instance_id'], ['name' => 'idx_source'])
                ->create();
        }

        if (!$this->hasTable('intra_federation_cache_enotf')) {
            $this->table('intra_federation_cache_enotf', $tableOptions)
                ->addColumn('source_instance_id', 'string', ['limit' => 36, 'null' => false])
                ->addColumn('remote_id', 'integer', ['null' => false])
                ->addColumn('cached_data', 'json', ['null' => false])
                ->addColumn('protocol_date', 'datetime', ['null' => true, 'default' => null])
                ->addColumn('cached_at', 'datetime', ['null' => false])
                ->addIndex(['source_instance_id', 'remote_id'], ['unique' => true, 'name' => 'idx_source_remote'])
                ->addIndex(['source_instance_id'], ['name' => 'idx_source'])
                ->addIndex(['protocol_date'], ['name' => 'idx_protocol_date'])
                ->create();
        }

        if (!$this->hasTable('intra_federation_cache_fire')) {
            $this->table('intra_federation_cache_fire', $tableOptions)
                ->addColumn('source_instance_id', 'string', ['limit' => 36, 'null' => false])
                ->addColumn('remote_id', 'integer', ['null' => false])
                ->addColumn('incident_number', 'string', ['limit' => 20, 'null' => true, 'default' => null])
                ->addColumn('cached_data', 'json', ['null' => false])
                ->addColumn('incident_date', 'datetime', ['null' => true, 'default' => null])
                ->addColumn('cached_at', 'datetime', ['null' => false])
                ->addIndex(['source_instance_id', 'remote_id'], ['unique' => true, 'name' => 'idx_source_remote'])
                ->addIndex(['source_instance_id'], ['name' => 'idx_source'])
                ->addIndex(['incident_date'], ['name' => 'idx_incident_date'])
                ->create();
        }

        if (!$this->hasTable('intra_federation_sync_log')) {
            $this->table('intra_federation_sync_log', $tableOptions)
                ->addColumn('link_id', 'integer', ['null' => false])
                ->addColumn('sync_type', 'enum', ['values' => ['personnel', 'enotf', 'fire'], 'null' => false])
                ->addColumn('status', 'enum', ['values' => ['success', 'error'], 'null' => false])
                ->addColumn('records_synced', 'integer', ['default' => 0, 'null' => false])
                ->addColumn('duration_ms', 'integer', ['null' => true, 'default' => null])
                ->addColumn('error_message', 'text', ['null' => true, 'default' => null])
                ->addColumn('synced_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
                ->addIndex(['link_id', 'synced_at'], ['name' => 'idx_link_synced'])
                ->create();
        }
    }
}
