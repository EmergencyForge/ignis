<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Protokoll-Erweiterung Oktober 2025: getrennte Haupt-/Nebendiagnosen und
 * Psych-Befund, Übergabeziel und -empfänger, zusätzliche Maßnahmen- und
 * Befundfelder (Entlastungspunktion, HWS-Immobilisation, Radialispuls,
 * Pulsregelmäßigkeit) sowie die Einsatzart `eart`.
 */
class AlterIntraEdivi07102025 extends AbstractMigration
{
    public function change(): void
    {
        // Reihenfolge entspricht den ursprünglichen Einzel-ALTERs — die
        // AFTER-Positionen bauen aufeinander auf.
        $this->table('intra_edivi')
            ->addColumn('diagnose_haupt',      'text',        ['null' => true, 'after' => 'medis'])
            ->addColumn('diagnose_weitere',    'text',        ['null' => true, 'after' => 'diagnose_haupt'])
            ->addColumn('psych',               'text',        ['null' => true, 'after' => 'medis'])
            ->addColumn('uebergabe_an',        'tinyinteger', ['limit' => 3, 'null' => true, 'after' => 'anmerkungen'])
            ->addColumn('uebergabe_ort',       'tinyinteger', ['limit' => 3, 'null' => true, 'after' => 'anmerkungen'])
            ->addColumn('entlastungspunktion', 'boolean',     ['null' => true, 'after' => 'awsicherung_2'])
            ->addColumn('hws_immo',            'boolean',     ['null' => true, 'after' => 'entlastungspunktion'])
            ->addColumn('c_puls_rad',          'tinyinteger', ['limit' => 3, 'null' => true, 'after' => 'c_ekg'])
            ->addColumn('c_puls_reg',          'tinyinteger', ['limit' => 3, 'null' => true, 'after' => 'c_ekg'])
            ->addColumn('eart',                'boolean',     ['null' => true, 'after' => 'eort'])
            ->update();
    }
}
