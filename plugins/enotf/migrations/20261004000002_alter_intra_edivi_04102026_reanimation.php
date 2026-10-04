<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

/**
 * Fügt die Reanimationssituation (7 Abschluss) zur intra_edivi Tabelle hinzu.
 *
 * - rea_status: 1=keine Reasituation, 2=durchgeführt, 3-6=nicht durchgeführt (Begründung)
 * - Details nur bei rea_status=2, Codes siehe Plugin\Enotf\Helpers\ReanimationCatalog
 * - Zeitfelder als 'HH:MM' wie symptombeginn_zeit
 */
class AlterIntraEdivi04102026Reanimation extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_edivi');

        if ($table->hasColumn('rea_status')) {
            return;
        }

        $tiny = ['limit' => MysqlAdapter::INT_TINY, 'null' => true];
        $zeit = ['limit' => 5, 'null' => true];

        $table
            ->addColumn('rea_status', 'integer', $tiny + ['after' => 'na_nachf'])
            ->addColumn('rea_ursache', 'integer', $tiny + ['after' => 'rea_status'])
            ->addColumn('rea_sport', 'integer', $tiny + ['after' => 'rea_ursache'])
            ->addColumn('rea_fr_eintreffen', 'string', $zeit + ['after' => 'rea_sport'])
            ->addColumn('rea_kollaps', 'integer', $tiny + ['after' => 'rea_fr_eintreffen'])
            ->addColumn('rea_hdm_durch', 'integer', $tiny + ['after' => 'rea_kollaps'])
            ->addColumn('rea_hdm_zeit', 'string', $zeit + ['after' => 'rea_hdm_durch'])
            ->addColumn('rea_defi', 'integer', $tiny + ['after' => 'rea_hdm_zeit'])
            ->addColumn('rea_rosc', 'integer', $tiny + ['after' => 'rea_defi'])
            ->addColumn('rea_kh_aufnahme', 'integer', $tiny + ['after' => 'rea_rosc'])
            ->addColumn('rea_tod_zeit', 'string', $zeit + ['after' => 'rea_kh_aufnahme'])
            ->addColumn('rea_erfolglos', 'integer', $tiny + ['after' => 'rea_tod_zeit'])
            ->update();
    }
}
