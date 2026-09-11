<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fahrzeugstamm wird intra-weit nutzbar: Kennzeichen-Spalte kommt hinzu,
 * `doctor` wird zum allgemeineren `rd_type` und die Tabelle wandert von
 * `intra_edivi_fahrzeuge` nach `intra_fahrzeuge`.
 */
class UpdateIntraEdiviFahrzeuge23062025 extends AbstractMigration
{
    public function change(): void
    {
        $this->table('intra_edivi_fahrzeuge')
            ->addColumn('kennzeichen', 'string', ['limit' => 255, 'null' => true])
            ->renameColumn('doctor', 'rd_type')
            ->rename('intra_fahrzeuge')
            ->update();
    }
}
