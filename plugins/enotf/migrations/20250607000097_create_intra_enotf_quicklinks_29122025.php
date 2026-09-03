<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Quicklinks fürs eNOTF-Dashboard: konfigurierbare Kacheln mit Titel, URL,
 * FontAwesome-Icon, Kategorie (Schnellzugriff/Verwaltung), Sortierung und
 * Spaltenbreite.
 */
class CreateIntraEnotfQuicklinks29122025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_enotf_quicklinks')) {
            return;
        }

        $this->table('intra_enotf_quicklinks', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('title',      'string',    ['limit' => 255, 'null' => false])
            ->addColumn('url',        'string',    ['limit' => 500, 'null' => false])
            ->addColumn('icon',       'string',    ['limit' => 100, 'null' => false, 'default' => 'fa-solid fa-link'])
            ->addColumn('category',   'enum',      ['values' => ['schnellzugriff', 'verwaltung'], 'default' => 'schnellzugriff'])
            ->addColumn('sort_order', 'integer',   ['null' => false, 'default' => 0])
            ->addColumn('col_width',  'string',    ['limit' => 20, 'null' => false, 'default' => 'col-6'])
            ->addColumn('active',     'boolean',   ['null' => false, 'default' => 1])
            ->addColumn('created_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['category', 'active'], ['name' => 'idx_category_active'])
            ->addIndex(['sort_order'],         ['name' => 'idx_sort_order'])
            ->create();
    }
}
