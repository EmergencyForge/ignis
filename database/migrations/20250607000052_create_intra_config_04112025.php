<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Zentrale Konfigurationstabelle: Key-Value-Einstellungen mit Typ, Kategorie,
 * Beschreibung und Anzeige-Reihenfolge; ersetzt hartcodierte Config-Werte.
 */
class CreateIntraConfig04112025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_config')) {
            return;
        }

        $this->table('intra_config', [
            'id'        => 'id',
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('config_key',    'string',   ['limit' => 100, 'comment' => 'Configuration key (e.g., SYSTEM_NAME)', 'null' => false])
            ->addColumn('config_value',  'text',     ['null' => true, 'comment' => 'Configuration value'])
            ->addColumn('config_type',   'string',   ['limit' => 50, 'default' => 'string', 'comment' => 'Type: string, boolean, integer, color, url', 'null' => false])
            ->addColumn('category',      'string',   ['limit' => 50, 'comment' => 'Category: basis, server, rp, funktionen', 'null' => false])
            ->addColumn('description',   'string',   ['limit' => 255, 'null' => true, 'comment' => 'Human-readable description'])
            ->addColumn('is_editable',   'boolean',  ['default' => 1, 'comment' => '0=read-only, 1=editable', 'null' => false])
            ->addColumn('display_order', 'integer',  ['default' => 0, 'comment' => 'Order for display in UI', 'null' => false])
            ->addColumn('updated_at',    'datetime', ['null' => true, 'default' => null, 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_by',    'integer',  ['null' => true, 'comment' => 'User ID who last updated'])
            // Das Legacy-SQL erzeugte zwei Unique-Indizes auf config_key:
            // einen über das Inline-UNIQUE (Name `config_key`) und einen
            // über UNIQUE KEY `idx_config_key` — beide exakt übernommen.
            ->addIndex(['config_key'], ['unique' => true, 'name' => 'config_key'])
            ->addIndex(['config_key'], ['unique' => true, 'name' => 'idx_config_key'])
            ->addIndex(['category'],   ['name' => 'idx_category'])
            ->addIndex(['updated_by'], ['name' => 'FK_intra_config_user'])
            ->addForeignKey('updated_by', 'intra_users', 'id', [
                'delete'     => 'SET_NULL',
                'constraint' => 'FK_intra_config_user',
            ])
            ->create();
    }
}
