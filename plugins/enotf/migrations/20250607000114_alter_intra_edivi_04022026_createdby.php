<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fügt die Spalte 'createdby' zur intra_edivi Tabelle hinzu.
 *
 * Werte:
 * - 1 = Protokoll durch EMD-Sync (Leitstelle) erstellt
 * - 2 = Protokoll durch User manuell erstellt
 *
 * Default ist 1, damit bestehende Protokolle als Leitstellen-Protokolle gelten.
 */
class AlterIntraEdivi04022026Createdby extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_edivi');

        if ($table->hasColumn('createdby')) {
            return;
        }

        $table->addColumn('createdby', 'boolean', [
            'null'    => false,
            'default' => 1,
            'after'   => 'created_at',
        ])->update();
    }
}
