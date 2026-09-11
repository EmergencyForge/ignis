<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Beladungs-Kacheln für Fahrzeuge: einzelne Beladungsgegenstände mit Menge,
 * einer Kategorie (intra_fahrzeuge_beladung_categories) zugeordnet.
 */
class CreateIntraFahrzeugeBeladungTiles23062025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_fahrzeuge_beladung_tiles')) {
            return;
        }

        $this->table('intra_fahrzeuge_beladung_tiles', [
            'id'        => 'id',
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('category',   'integer',   ['default' => 0, 'null' => false])
            ->addColumn('amount',     'integer',   ['default' => 0, 'null' => false])
            ->addColumn('title',      'string',    ['limit' => 255, 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addIndex(['category'], ['name' => 'FK_beladung_categories'])
            ->addForeignKey('category', 'intra_fahrzeuge_beladung_categories', 'id', [
                'delete'     => 'CASCADE',
                'update'     => 'CASCADE',
                'constraint' => 'FK_beladung_categories',
            ])
            ->create();
    }
}
