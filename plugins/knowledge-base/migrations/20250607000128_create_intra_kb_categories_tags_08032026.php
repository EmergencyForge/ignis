<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Hierarchische Kategorien und Tags für die Wissensdatenbank:
 *
 *   - `intra_kb_categories` — Baumstruktur via parent_id
 *   - `intra_kb_tags`       — flache Tags mit Farbe
 *   - `intra_kb_entry_tags` — n:m-Zuordnung Einträge ↔ Tags
 *
 * Zusätzlich bekommt `intra_kb_entries` eine `category_id`-Spalte samt Index.
 */
class CreateIntraKbCategoriesTags08032026 extends AbstractMigration
{
    public function change(): void
    {
        // 1. Kategorien-Tabelle (hierarchisch via parent_id)
        if (!$this->hasTable('intra_kb_categories')) {
            $this->table('intra_kb_categories', [
                'signed'    => true,
                'engine'    => 'InnoDB',
                'encoding'  => 'utf8mb4',
                'collation' => 'utf8mb4_general_ci',
            ])
                ->addColumn('parent_id',  'integer',   ['null' => true, 'default' => null])
                ->addColumn('name',       'string',    ['limit' => 100, 'null' => false])
                ->addColumn('slug',       'string',    ['limit' => 100, 'null' => false])
                ->addColumn('icon',       'string',    ['limit' => 50, 'null' => true, 'default' => null])
                ->addColumn('sort_order', 'integer',   ['default' => 0, 'null' => false])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['parent_id'], ['name' => 'idx_parent'])
                ->addIndex(['slug'],      ['name' => 'idx_slug'])
                ->addForeignKey('parent_id', 'intra_kb_categories', 'id', [
                    'delete'     => 'SET_NULL',
                    'constraint' => 'fk_kb_cat_parent',
                ])
                ->create();
        }

        // 2. Tags-Tabelle
        if (!$this->hasTable('intra_kb_tags')) {
            $this->table('intra_kb_tags', [
                'signed'    => true,
                'engine'    => 'InnoDB',
                'encoding'  => 'utf8mb4',
                'collation' => 'utf8mb4_general_ci',
            ])
                ->addColumn('name',       'string',    ['limit' => 50, 'null' => false])
                ->addColumn('color',      'string',    ['limit' => 7, 'default' => '#6c757d', 'null' => false])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['name'], ['unique' => true, 'name' => 'name'])
                ->create();
        }

        // 3. Junction-Tabelle: Einträge ↔ Tags (n:m)
        if (!$this->hasTable('intra_kb_entry_tags')) {
            $this->table('intra_kb_entry_tags', [
                'id'          => false,
                'primary_key' => ['entry_id', 'tag_id'],
                'engine'      => 'InnoDB',
                'encoding'    => 'utf8mb4',
                'collation'   => 'utf8mb4_general_ci',
            ])
                ->addColumn('entry_id', 'integer')
                ->addColumn('tag_id',   'integer')
                ->addForeignKey('entry_id', 'intra_kb_entries', 'id', [
                    'delete'     => 'CASCADE',
                    'constraint' => 'fk_kb_et_entry',
                ])
                ->addForeignKey('tag_id', 'intra_kb_tags', 'id', [
                    'delete'     => 'CASCADE',
                    'constraint' => 'fk_kb_et_tag',
                ])
                ->create();
        }

        // 4. category_id-Spalte zu kb_entries hinzufügen
        $entries = $this->table('intra_kb_entries');
        if (!$entries->hasColumn('category_id')) {
            $entries
                ->addColumn('category_id', 'integer', ['null' => true, 'default' => null, 'after' => 'type'])
                ->addIndex(['category_id'], ['name' => 'idx_category'])
                ->update();
        }
    }
}
