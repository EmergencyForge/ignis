<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Eingereichte Anträge des dynamischen Antragssystems: Kopfzeile mit
 * Antragsteller, Typ und Bearbeitungsstatus (cirs_*). Die Formularwerte
 * selbst liegen in intra_antraege_daten.
 */
class CreateIntraAntraege30092025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_antraege')) {
            return;
        }

        $this->table('intra_antraege', [
            'id'        => 'id',
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('uniqueid',      'string',   ['limit' => 10, 'null' => false])
            ->addColumn('antragstyp_id', 'integer', ['null' => false])
            ->addColumn('name_dn',       'string',   ['limit' => 255, 'null' => false])
            ->addColumn('dienstgrad',    'string',   ['limit' => 100, 'null' => true])
            ->addColumn('discordid',     'string',   ['limit' => 255, 'null' => true])
            ->addColumn('cirs_status',   'boolean',  ['default' => 0, 'comment' => '0=Bearbeitung, 1=Abgelehnt, 2=Aufgeschoben, 3=Angenommen', 'null' => false])
            ->addColumn('cirs_manager',  'string',   ['limit' => 255, 'null' => true])
            ->addColumn('cirs_text',     'text',     ['null' => true, 'comment' => 'Bemerkung des Bearbeiters'])
            ->addColumn('time_added',    'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addColumn('cirs_time',     'datetime', ['null' => true])
            ->addIndex(['uniqueid'],      ['unique' => true, 'name' => 'uniqueid'])
            ->addIndex(['cirs_status'],   ['name' => 'idx_status'])
            ->addIndex(['uniqueid'],      ['name' => 'idx_uniqueid'])
            ->addIndex(['antragstyp_id'], ['name' => 'idx_typ'])
            ->addForeignKey('antragstyp_id', 'intra_antrag_typen', 'id')
            ->create();
    }
}
