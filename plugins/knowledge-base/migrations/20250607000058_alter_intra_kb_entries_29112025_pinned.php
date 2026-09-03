<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fügt `is_pinned` samt Index zu `intra_kb_entries` hinzu — für Installationen,
 * deren Tabelle vor Einführung des Pinnens angelegt wurde. Neuere Installs
 * haben die Spalte bereits aus der Create-Migration; dann passiert hier nichts.
 */
class AlterIntraKbEntries29112025Pinned extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_kb_entries');
        if ($table->hasColumn('is_pinned')) {
            return;
        }

        $table
            ->addColumn('is_pinned', 'boolean', ['default' => 0, 'null' => false, 'after' => 'mass_durchfuehrung'])
            ->addIndex(['is_pinned'], ['name' => 'idx_pinned'])
            ->update();
    }
}
