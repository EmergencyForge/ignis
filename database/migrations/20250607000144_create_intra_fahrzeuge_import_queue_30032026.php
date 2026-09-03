<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

/**
 * Import-Warteschlange für Fahrzeuge aus dem EMD: eingehende Datensätze
 * landen als pending und werden von einem Admin angenommen oder abgelehnt.
 * raw_data hält den kompletten Original-Payload als JSON.
 */
class CreateIntraFahrzeugeImportQueue30032026 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_fahrzeuge_import_queue')) {
            return;
        }

        $this->table('intra_fahrzeuge_import_queue', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('emd_vehicle_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('identifier', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('veh_type', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('rd_type', 'integer', ['limit' => MysqlAdapter::INT_TINY, 'default' => 0, 'null' => false])
            ->addColumn('department', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('valuelong', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('job', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('image', 'string', ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('funkkanal', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->addColumn('raw_data', 'json', ['null' => true, 'default' => null])
            ->addColumn('status', 'enum', [
                'values'  => ['pending', 'accepted', 'rejected'],
                'default' => 'pending',
                'null'    => false,
            ])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('processed_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('processed_by', 'integer', ['null' => true, 'default' => null])
            ->addIndex(['status'], ['name' => 'idx_status'])
            ->addForeignKey('processed_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
            ->create();
    }
}
