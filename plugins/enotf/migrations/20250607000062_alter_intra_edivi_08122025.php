<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Einsatzzeiten und Ortsangaben: Zeitstempel-Slots des Einsatzverlaufs
 * (Alarm, S1–S8, Patientenkontakt, Ende) hinter `transportziel` sowie
 * POI-/Adressfelder für Einsatzort und Transportziel hinter `eort`.
 */
class AlterIntraEdivi08122025 extends AbstractMigration
{
    public function change(): void
    {
        $this->table('intra_edivi')
            ->addColumn('salarm',         'string', ['limit' => 255, 'null' => true, 'after' => 'transportziel'])
            ->addColumn('s1',             'string', ['limit' => 255, 'null' => true, 'after' => 'salarm'])
            ->addColumn('s2',             'string', ['limit' => 255, 'null' => true, 'after' => 's1'])
            ->addColumn('s3',             'string', ['limit' => 255, 'null' => true, 'after' => 's2'])
            ->addColumn('s4',             'string', ['limit' => 255, 'null' => true, 'after' => 's3'])
            ->addColumn('spat',           'string', ['limit' => 255, 'null' => true, 'after' => 's4'])
            ->addColumn('s7',             'string', ['limit' => 255, 'null' => true, 'after' => 'spat'])
            ->addColumn('s8',             'string', ['limit' => 255, 'null' => true, 'after' => 's7'])
            ->addColumn('sende',          'string', ['limit' => 255, 'null' => true, 'after' => 's8'])
            ->addColumn('transp_poi',     'string', ['limit' => 255, 'null' => true, 'after' => 'eort'])
            ->addColumn('transp_adresse', 'text',   ['null' => true, 'after' => 'transp_poi'])
            ->addColumn('ziel_poi',       'string', ['limit' => 255, 'null' => true, 'after' => 'transp_adresse'])
            ->addColumn('ziel_adresse',   'text',   ['null' => true, 'after' => 'ziel_poi'])
            ->update();
    }
}
