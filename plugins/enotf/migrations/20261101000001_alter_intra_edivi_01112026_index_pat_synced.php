<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Index auf intra_edivi.pat_synced. Der EMD-Sync fragt bei jedem Heartbeat
 * (alle 5 s) nach pat_synced = 2 und las ohne Index jedes Mal die ganze
 * Tabelle.
 */
class AlterIntraEdivi01112026IndexPatSynced extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_edivi');

        if ($table->hasIndexByName('idx_edivi_pat_synced')) {
            return;
        }

        $table->addIndex(['pat_synced'], ['name' => 'idx_edivi_pat_synced'])->update();
    }
}
