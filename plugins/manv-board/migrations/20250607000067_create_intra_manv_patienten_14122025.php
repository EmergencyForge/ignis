<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * MANV-Patienten: Patienten je Lage mit Sichtungskategorie (SK1–SK4/tot),
 * Transportdaten und Behandlungsnotizen.
 */
class CreateIntraManvPatienten14122025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_manv_patienten')) {
            return;
        }

        $this->table('intra_manv_patienten', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
            'comment'   => 'MANV-Patienten',
        ])
            ->addColumn('manv_lage_id',      'integer',  ['null' => false])
            ->addColumn('patienten_nummer',  'string',   ['limit' => 50, 'null' => false, 'comment' => 'z.B. MANV-001'])
            ->addColumn('name',              'string',   ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('vorname',           'string',   ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('geburtsdatum',      'date',     ['null' => true, 'default' => null])
            ->addColumn('geschlecht',        'enum',     ['values' => ['m', 'w', 'd', 'unbekannt'], 'null' => true, 'default' => 'unbekannt'])
            ->addColumn('sichtungskategorie', 'enum',    [
                'values'  => ['SK1', 'SK2', 'SK3', 'SK4', 'tot'],
                'null'    => true,
                'default' => null,
                'comment' => 'SK1=rot/sofort, SK2=gelb/dringend, SK3=grün/später, SK4=blau/abwartend',
            ])
            ->addColumn('sichtungskategorie_zeit',           'datetime', ['null' => true, 'default' => null])
            ->addColumn('sichtungskategorie_geaendert_von',  'integer',  ['null' => true, 'default' => null])
            ->addColumn('transportmittel',         'string',   ['limit' => 100, 'null' => true, 'default' => null, 'comment' => 'RTW, NAW, RTH, etc.'])
            ->addColumn('transportmittel_rufname', 'string',   ['limit' => 100, 'null' => true, 'default' => null, 'comment' => 'z.B. Florian ABC 83-1'])
            ->addColumn('fahrzeug_lokalisation',   'string',   ['limit' => 255, 'null' => true, 'default' => null, 'comment' => 'Position an der Einsatzstelle'])
            ->addColumn('transportziel',           'string',   ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('transport_abfahrt',       'datetime', ['null' => true, 'default' => null])
            ->addColumn('transport_ankunft',       'datetime', ['null' => true, 'default' => null])
            ->addColumn('verletzungen',            'text',     ['null' => true, 'default' => null])
            ->addColumn('massnahmen',              'text',     ['null' => true, 'default' => null])
            ->addColumn('notizen',                 'text',     ['null' => true, 'default' => null])
            ->addColumn('erstellt_am',             'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('erstellt_von',            'integer',  ['null' => true, 'default' => null])
            ->addColumn('geaendert_am',            'datetime', ['null' => true, 'default' => null, 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('geaendert_von',           'integer',  ['null' => true, 'default' => null])
            ->addIndex(['manv_lage_id'],       ['name' => 'idx_manv_lage'])
            ->addIndex(['patienten_nummer'],   ['name' => 'idx_patienten_nummer'])
            ->addIndex(['sichtungskategorie'], ['name' => 'idx_sichtungskategorie'])
            ->addForeignKey('manv_lage_id', 'intra_manv_lagen', 'id', [
                'delete'     => 'CASCADE',
                'constraint' => 'fk_manv_patient_lage',
            ])
            ->create();
    }
}
