<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Sitrep-Herkunft und Sync-Status: source unterscheidet Leitstellen- von
 * lokalen Meldungen, synced markiert lokale Sitreps, die bereits an die
 * Leitstelle zurückgemeldet wurden.
 */
class AlterIntraFireIncidentSitreps10022026AddSourceSynced extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_fire_incident_sitreps');
        $changed = false;

        if (!$table->hasColumn('source')) {
            $table->addColumn('source', 'string', ['limit' => 50, 'null' => true, 'default' => null, 'after' => 'created_by']);
            $changed = true;
        }
        if (!$table->hasColumn('synced')) {
            $table->addColumn('synced', 'boolean', ['default' => 0, 'null' => false, 'after' => 'source']);
            $changed = true;
        }

        if ($changed) {
            $table->update();
        }
    }
}
