<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

/**
 * Personal-Dokumente (Urkunden, Suspendierungen etc.): eindeutige docid,
 * Empfänger- und Aussteller-Daten als Snapshot, optional an eine
 * Personalakte gebunden (profileid, CASCADE beim Löschen der Akte).
 */
class CreateIntraMitarbeiterDokumente07062025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_mitarbeiter_dokumente')) {
            return;
        }

        $this->table('intra_mitarbeiter_dokumente', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('docid',             'integer')
            ->addColumn('type',              'tinyinteger', ['limit' => 2, 'default' => 0, 'null' => false])
            ->addColumn('anrede',            'boolean',     ['default' => 0, 'null' => false])
            ->addColumn('erhalter',          'string',      ['limit' => 255, 'null' => true])
            ->addColumn('inhalt',            'text',        ['limit' => MysqlAdapter::TEXT_LONG, 'null' => true])
            ->addColumn('suspendtime',       'date',        ['null' => true])
            ->addColumn('erhalter_gebdat',   'date',        ['null' => true])
            ->addColumn('erhalter_rang',     'tinyinteger', ['limit' => 2, 'null' => true])
            ->addColumn('erhalter_rang_rd',  'tinyinteger', ['limit' => 2, 'null' => true])
            ->addColumn('erhalter_quali',    'tinyinteger', ['limit' => 2, 'null' => true])
            ->addColumn('ausstellungsdatum', 'date',        ['null' => true])
            ->addColumn('ausstellerid',      'integer')
            ->addColumn('aussteller_name',   'string',      ['limit' => 255, 'null' => true])
            ->addColumn('aussteller_rang',   'tinyinteger', ['limit' => 2, 'null' => true])
            ->addColumn('timestamp',         'timestamp',   ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addColumn('profileid',         'integer',     ['null' => true])
            ->addColumn('discordid',         'string',      ['limit' => 255, 'null' => true])
            ->addIndex(['docid'],     ['unique' => true, 'name' => 'docid'])
            ->addIndex(['profileid'], ['name' => 'FK_intra_mitarbeiter_dokumente_intra_mitarbeiter'])
            ->addForeignKey('profileid', 'intra_mitarbeiter', 'id', [
                'constraint' => 'FK_intra_mitarbeiter_dokumente_intra_mitarbeiter',
                'delete'     => 'CASCADE',
                'update'     => 'CASCADE',
            ])
            ->create();
    }
}
