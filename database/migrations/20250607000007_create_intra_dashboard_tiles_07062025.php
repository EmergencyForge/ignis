<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Dashboard-Kacheln: Links mit Icon und Priorität, je einer Kategorie
 * zugeordnet (FK mit CASCADE — Kategorie löschen räumt die Kacheln mit ab).
 */
class CreateIntraDashboardTiles07062025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_dashboard_tiles')) {
            return;
        }

        $this->table('intra_dashboard_tiles', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('category',   'integer',   ['default' => 0, 'null' => false])
            ->addColumn('title',      'string',    ['limit' => 255, 'null' => false])
            ->addColumn('url',        'string',    ['limit' => 255, 'default' => '#', 'null' => false])
            ->addColumn('icon',       'string',    ['limit' => 255, 'default' => 'fa-solid fa-up-right-from-square', 'null' => false])
            ->addColumn('priority',   'integer',   ['default' => 0, 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addIndex(['category'], ['name' => 'FK_intra_dashboard_tiles_intra_dashboard_categories'])
            ->addForeignKey('category', 'intra_dashboard_categories', 'id', [
                'constraint' => 'FK_intra_dashboard_tiles_intra_dashboard_categories',
                'delete'     => 'CASCADE',
                'update'     => 'CASCADE',
            ])
            ->create();
    }
}
