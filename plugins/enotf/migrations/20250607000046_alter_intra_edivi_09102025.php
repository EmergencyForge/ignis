<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Protokoll-Erweiterung: NA-Nachforderung, Einsatzbesonderheiten,
 * Rettungstechnik und Lagerung sowie die Maßnahmen-Flags des E-Schemas
 * (Wärmeerhalt, Reposition, Verband, Kühlung, Narkose, Tourniquet, CPR u. a.)
 * plus Rekap-Zeit und kritische Blutung im C-Schema.
 */
class AlterIntraEdivi09102025 extends AbstractMigration
{
    public function change(): void
    {
        // Reihenfolge entspricht den ursprünglichen Einzel-ALTERs — die
        // AFTER-Positionen bauen aufeinander auf.
        $this->table('intra_edivi')
            ->addColumn('na_nachf',        'boolean',     ['null' => true, 'after' => 'transportziel'])
            ->addColumn('ebesonderheiten', 'text',        ['null' => true, 'after' => 'na_nachf'])
            ->addColumn('rettungstechnik', 'text',        ['null' => true, 'after' => 'sz_toleranz_2'])
            ->addColumn('lagerung',        'tinyinteger', ['limit' => 2, 'null' => true, 'after' => 'sz_toleranz_2'])
            ->addColumn('waerme_passiv',   'boolean',     ['null' => true, 'after' => 'rettungstechnik'])
            ->addColumn('e_reposition',    'boolean',     ['null' => true, 'after' => 'waerme_passiv'])
            ->addColumn('e_verband',       'boolean',     ['null' => true, 'after' => 'e_reposition'])
            ->addColumn('e_krintervention', 'boolean',    ['null' => true, 'after' => 'e_verband'])
            ->addColumn('e_kuehlung',      'boolean',     ['null' => true, 'after' => 'e_krintervention'])
            ->addColumn('waerme_aktiv',    'boolean',     ['null' => true, 'after' => 'e_kuehlung'])
            ->addColumn('e_narkose',       'boolean',     ['null' => true, 'after' => 'waerme_aktiv'])
            ->addColumn('e_tourniquet',    'boolean',     ['null' => true, 'after' => 'e_narkose'])
            ->addColumn('e_cpr',           'boolean',     ['null' => true, 'after' => 'e_tourniquet'])
            ->addColumn('c_rekap',         'boolean',     ['null' => true, 'after' => 'c_puls_rad'])
            ->addColumn('c_blutung',       'boolean',     ['null' => true, 'after' => 'c_rekap'])
            ->update();
    }
}
