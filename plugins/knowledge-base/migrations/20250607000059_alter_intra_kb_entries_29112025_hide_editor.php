<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fügt `hide_editor` zu `intra_kb_entries` hinzu: erlaubt pro Artikel die
 * Anonymisierung der Bearbeiter-Namen (nur Admins können das umschalten).
 */
class AlterIntraKbEntries29112025HideEditor extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_kb_entries');
        if ($table->hasColumn('hide_editor')) {
            return;
        }

        $table
            ->addColumn('hide_editor', 'boolean', ['default' => 0, 'null' => false, 'after' => 'is_pinned'])
            ->update();
    }
}
