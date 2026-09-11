<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Beladungs-Kategorien für Fahrzeuge: gruppiert Beladungs-Kacheln
 * (intra_fahrzeuge_beladung_tiles) nach Fahrzeugtyp.
 */
class CreateIntraFahrzeugeBeladungCategories23062025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_fahrzeuge_beladung_categories')) {
            return;
        }

        $this->table('intra_fahrzeuge_beladung_categories', [
            'id'        => 'id',
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('title',      'string',    ['limit' => 255, 'null' => false])
            ->addColumn('type',       'boolean',   ['default' => 0, 'null' => false])
            ->addColumn('priority',   'integer',   ['default' => 0, 'null' => false])
            ->addColumn('veh_type',   'string',    ['limit' => 255, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->create();
    }
}
