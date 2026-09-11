<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

/**
 * Kerntabelle des eDIVI-Protokolls: ein Datensatz pro Einsatzprotokoll mit
 * Patientendaten, ABCDE-Befunden (Atemweg, Beatmung, Kreislauf, neurologischer
 * Status, Verletzungsmuster), Vitalwerten, Medikation, beteiligten Fahrzeugen
 * und QM-Status (Freigabe, Bearbeiter, Sichtbarkeit).
 */
class CreateIntraEdivi07062025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_edivi')) {
            return;
        }

        $this->table('intra_edivi', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('patname',            'string',      ['limit' => 255, 'null' => true])
            ->addColumn('patgebdat',          'date',        ['null' => true])
            ->addColumn('patsex',             'boolean',     ['null' => true])
            ->addColumn('edatum',             'date',        ['null' => true])
            ->addColumn('ezeit',              'string',      ['limit' => 255, 'null' => true])
            ->addColumn('enr',                'string',      ['limit' => 255, 'null' => false])
            ->addColumn('eort',               'string',      ['limit' => 255, 'null' => true])
            ->addColumn('sendezeit',          'datetime',    ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('awfrei_1',           'boolean',     ['null' => true])
            ->addColumn('awfrei_2',           'boolean',     ['null' => true])
            ->addColumn('awfrei_3',           'boolean',     ['null' => true])
            ->addColumn('awsicherung_1',      'boolean',     ['null' => true])
            ->addColumn('awsicherung_2',      'boolean',     ['null' => true])
            ->addColumn('awsicherung_neu',    'boolean',     ['null' => true])
            ->addColumn('zyanose_1',          'boolean',     ['null' => true])
            ->addColumn('zyanose_2',          'boolean',     ['null' => true])
            ->addColumn('o2gabe',             'tinyinteger', ['limit' => 15, 'null' => true, 'default' => 0])
            ->addColumn('b_symptome',         'tinyinteger', ['limit' => 4, 'null' => true])
            ->addColumn('b_auskult',          'tinyinteger', ['limit' => 3, 'null' => true])
            ->addColumn('b_beatmung',         'tinyinteger', ['limit' => 3, 'null' => true])
            ->addColumn('spo2',               'string',      ['limit' => 255, 'null' => true])
            ->addColumn('atemfreq',           'string',      ['limit' => 255, 'null' => true])
            ->addColumn('etco2',              'string',      ['limit' => 255, 'null' => true])
            ->addColumn('c_kreislauf',        'tinyinteger', ['limit' => 2, 'null' => true])
            ->addColumn('rrsys',              'string',      ['limit' => 255, 'null' => true])
            ->addColumn('rrdias',             'string',      ['limit' => 255, 'null' => true])
            ->addColumn('herzfreq',           'string',      ['limit' => 255, 'null' => true])
            ->addColumn('c_ekg',              'tinyinteger', ['limit' => 9, 'null' => true])
            ->addColumn('c_zugang_art_1',     'tinyinteger', ['limit' => 9, 'null' => true])
            ->addColumn('c_zugang_gr_1',      'tinyinteger', ['limit' => 9, 'null' => true])
            ->addColumn('c_zugang_ort_1',     'string',      ['limit' => 255, 'null' => true])
            ->addColumn('c_zugang_art_2',     'tinyinteger', ['limit' => 9, 'null' => true])
            ->addColumn('c_zugang_gr_2',      'tinyinteger', ['limit' => 9, 'null' => true])
            ->addColumn('c_zugang_ort_2',     'string',      ['limit' => 255, 'null' => true])
            ->addColumn('c_zugang_art_3',     'tinyinteger', ['limit' => 9, 'null' => true])
            ->addColumn('c_zugang_gr_3',      'tinyinteger', ['limit' => 9, 'null' => true])
            ->addColumn('c_zugang_ort_3',     'string',      ['limit' => 255, 'null' => true])
            ->addColumn('d_bewusstsein',      'tinyinteger', ['limit' => 3, 'null' => true])
            ->addColumn('d_pupillenw_1',      'tinyinteger', ['limit' => 3, 'null' => true])
            ->addColumn('d_pupillenw_2',      'tinyinteger', ['limit' => 3, 'null' => true])
            ->addColumn('d_lichtreakt_1',     'tinyinteger', ['limit' => 2, 'null' => true])
            ->addColumn('d_lichtreakt_2',     'tinyinteger', ['limit' => 2, 'null' => true])
            ->addColumn('d_gcs_1',            'tinyinteger', ['limit' => 3, 'null' => true])
            ->addColumn('d_gcs_2',            'tinyinteger', ['limit' => 4, 'null' => true])
            ->addColumn('d_gcs_3',            'tinyinteger', ['limit' => 5, 'null' => true])
            ->addColumn('d_ex_1',             'tinyinteger', ['limit' => 2, 'null' => true])
            ->addColumn('bz',                 'string',      ['limit' => 255, 'null' => true])
            ->addColumn('temp',               'string',      ['limit' => 255, 'null' => true])
            ->addColumn('v_muster_k',         'tinyinteger', ['limit' => 3, 'null' => true])
            ->addColumn('v_muster_k1',        'tinyinteger', ['limit' => 2, 'null' => true])
            ->addColumn('v_muster_w',         'tinyinteger', ['limit' => 3, 'null' => true])
            ->addColumn('v_muster_w1',        'tinyinteger', ['limit' => 2, 'null' => true])
            ->addColumn('v_muster_t',         'tinyinteger', ['limit' => 3, 'null' => true])
            ->addColumn('v_muster_t1',        'tinyinteger', ['limit' => 2, 'null' => true])
            ->addColumn('v_muster_a',         'tinyinteger', ['limit' => 3, 'null' => true])
            ->addColumn('v_muster_a1',        'tinyinteger', ['limit' => 2, 'null' => true])
            ->addColumn('v_muster_al',        'tinyinteger', ['limit' => 3, 'null' => true])
            ->addColumn('v_muster_al1',       'tinyinteger', ['limit' => 2, 'null' => true])
            ->addColumn('v_muster_ar',        'tinyinteger', ['limit' => 3, 'null' => true])
            ->addColumn('v_muster_ar1',       'tinyinteger', ['limit' => 2, 'null' => true])
            ->addColumn('v_muster_bl',        'tinyinteger', ['limit' => 3, 'null' => true])
            ->addColumn('v_muster_bl1',       'tinyinteger', ['limit' => 2, 'null' => true])
            ->addColumn('v_muster_br',        'tinyinteger', ['limit' => 3, 'null' => true])
            ->addColumn('v_muster_br1',       'tinyinteger', ['limit' => 2, 'null' => true])
            ->addColumn('sz_nrs',             'tinyinteger', ['limit' => 2, 'null' => true])
            ->addColumn('sz_toleranz_1',      'tinyinteger', ['limit' => 2, 'null' => true])
            ->addColumn('sz_toleranz_2',      'tinyinteger', ['limit' => 2, 'null' => true])
            ->addColumn('medis',              'text',        ['limit' => MysqlAdapter::TEXT_LONG, 'null' => true])
            ->addColumn('diagnose',           'text',        ['null' => true])
            ->addColumn('anmerkungen',        'text',        ['null' => true])
            ->addColumn('pfname',             'string',      ['limit' => 255, 'null' => true])
            ->addColumn('prot_by',            'boolean',     ['null' => true])
            ->addColumn('fzg_transp',         'string',      ['limit' => 255, 'null' => true])
            ->addColumn('fzg_transp_perso',   'string',      ['limit' => 255, 'null' => true])
            ->addColumn('fzg_transp_perso_2', 'string',      ['limit' => 255, 'null' => true])
            ->addColumn('fzg_na',             'string',      ['limit' => 255, 'null' => true])
            ->addColumn('fzg_na_perso',       'string',      ['limit' => 255, 'null' => true])
            ->addColumn('fzg_na_perso_2',     'string',      ['limit' => 255, 'null' => true])
            ->addColumn('fzg_sonst',          'string',      ['limit' => 255, 'null' => true])
            ->addColumn('naname',             'string',      ['limit' => 255, 'null' => true])
            ->addColumn('transportziel',      'string',      ['limit' => 255, 'null' => true])
            ->addColumn('protokoll_status',   'tinyinteger', ['limit' => 3, 'null' => true, 'default' => 0])
            ->addColumn('bearbeiter',         'string',      ['limit' => 255, 'null' => true])
            ->addColumn('qmkommentar',        'text',        ['null' => true])
            ->addColumn('freigegeben',        'boolean',     ['null' => true, 'default' => 0])
            ->addColumn('freigeber_name',     'string',      ['limit' => 255, 'null' => true])
            ->addColumn('last_edit',          'timestamp',   ['null' => true, 'default' => null])
            ->addColumn('hidden',             'boolean',     ['null' => false, 'default' => 0])
            ->create();
    }
}
