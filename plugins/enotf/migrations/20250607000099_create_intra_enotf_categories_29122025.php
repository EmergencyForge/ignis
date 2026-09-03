<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Kategorien für die eNOTF-Quicklinks: Name, eindeutiger Slug, Sortierung
 * und Aktiv-Flag. Löst die fest kodierte ENUM-Kategorie der Quicklinks ab.
 */
class CreateIntraEnotfCategories29122025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_enotf_categories')) {
            return;
        }

        $this->table('intra_enotf_categories', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('name',       'string',    ['limit' => 100, 'null' => false])
            ->addColumn('slug',       'string',    ['limit' => 100, 'null' => false])
            ->addColumn('sort_order', 'integer',   ['null' => false, 'default' => 0])
            ->addColumn('active',     'boolean',   ['null' => false, 'default' => 1])
            ->addColumn('created_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['slug'],                 ['unique' => true, 'name' => 'unique_slug'])
            ->addIndex(['active', 'sort_order'], ['name' => 'idx_active_sort'])
            ->create();
    }
}
