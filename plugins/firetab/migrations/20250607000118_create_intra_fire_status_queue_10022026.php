<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Queue für ausgehende Status-Änderungen an die Leitstelle: pro Fahrzeug
 * der neue EMD-Status, abgearbeitete Einträge werden über delivered
 * markiert.
 */
class CreateIntraFireStatusQueue10022026 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_fire_status_queue')) {
            return;
        }

        $this->table('intra_fire_status_queue', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('vehicle_id', 'integer', ['null' => false])
            ->addColumn('vehicle_name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('incident_number', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('new_status', 'string', ['limit' => 10, 'null' => false])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('delivered', 'boolean', ['default' => 0, 'null' => false])
            ->addIndex(['delivered'], ['name' => 'idx_delivered'])
            ->addIndex(['vehicle_id'], ['name' => 'idx_vehicle'])
            ->addForeignKey('vehicle_id', 'intra_fahrzeuge', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
