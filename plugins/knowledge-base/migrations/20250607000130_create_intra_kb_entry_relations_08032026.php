<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Verknüpfte KB-Einträge (Querverweise).
 *
 * Bidirektionale Verknüpfung: Wenn A mit B verknüpft ist, ist B auch mit A
 * verknüpft. Nur eine Richtung wird gespeichert (entry_id < related_entry_id).
 */
class CreateIntraKbEntryRelations08032026 extends AbstractMigration
{
    public function up(): void
    {
        if ($this->hasTable('intra_kb_entry_relations')) {
            return;
        }

        $this->table('intra_kb_entry_relations', [
            'id'          => false,
            'primary_key' => ['entry_id', 'related_entry_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('entry_id',         'integer')
            ->addColumn('related_entry_id', 'integer')
            ->addColumn('created_at',       'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('entry_id', 'intra_kb_entries', 'id', [
                'delete'     => 'CASCADE',
                'constraint' => 'fk_kb_rel_entry',
            ])
            ->addForeignKey('related_entry_id', 'intra_kb_entries', 'id', [
                'delete'     => 'CASCADE',
                'constraint' => 'fk_kb_rel_related',
            ])
            ->create();

        // CHECK-Constraints kennt die Phinx-Tabellen-API nicht — der unbenannte
        // Constraint bekommt so denselben Auto-Namen wie beim Inline-CHECK.
        $this->execute('ALTER TABLE `intra_kb_entry_relations` ADD CHECK (`entry_id` < `related_entry_id`)');
    }

    public function down(): void
    {
        $this->table('intra_kb_entry_relations')->drop()->save();
    }
}
