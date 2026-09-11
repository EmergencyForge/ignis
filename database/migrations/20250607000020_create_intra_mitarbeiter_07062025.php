<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

/**
 * Personalakten: Stammdaten (Name, Geburtsdatum, Charakter-ID, Dienstnummer),
 * Kontaktdaten sowie Verweise auf Dienstgrad, FW- und RD-Qualifikation.
 * Fachdienste liegen als JSON-Liste in fachdienste.
 */
class CreateIntraMitarbeiter07062025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_mitarbeiter')) {
            return;
        }

        $this->table('intra_mitarbeiter', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('fullname',    'string',   ['limit' => 255, 'null' => false])
            ->addColumn('gebdatum',    'date', ['null' => false])
            ->addColumn('charakterid', 'string',   ['limit' => 255])
            ->addColumn('geschlecht',  'boolean', ['null' => false])
            ->addColumn('forumprofil', 'integer',  ['limit' => 5, 'null' => true])
            ->addColumn('discordtag',  'string',   ['limit' => 255, 'null' => true])
            ->addColumn('telefonnr',   'string',   ['limit' => 255, 'null' => true])
            ->addColumn('dienstnr',    'string',   ['limit' => 255, 'null' => false])
            ->addColumn('einstdatum',  'date', ['null' => false])
            ->addColumn('dienstgrad',  'integer',  ['default' => 0, 'null' => false])
            ->addColumn('qualifw2',    'integer',  ['default' => 0, 'null' => false])
            ->addColumn('qualird',     'integer',  ['default' => 0, 'null' => false])
            ->addColumn('zusatz',      'string',   ['limit' => 255, 'null' => true])
            ->addColumn('fachdienste', 'text',     ['limit' => MysqlAdapter::TEXT_LONG, 'null' => true])
            ->addColumn('createdate',  'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addIndex(['dienstnr'],   ['unique' => true, 'name' => 'dienstnr'])
            ->addIndex(['dienstgrad'], ['name' => 'FK_intra_mitarbeiter_intra_mitarbeiter_dienstgrade'])
            ->addIndex(['qualifw2'],   ['name' => 'FK_intra_mitarbeiter_intra_mitarbeiter_fwquali'])
            ->addIndex(['qualird'],    ['name' => 'FK_intra_mitarbeiter_intra_mitarbeiter_rdquali'])
            ->addForeignKey('dienstgrad', 'intra_mitarbeiter_dienstgrade', 'id', [
                'constraint' => 'FK_intra_mitarbeiter_intra_mitarbeiter_dienstgrade',
                'delete'     => 'NO_ACTION',
                'update'     => 'CASCADE',
            ])
            ->addForeignKey('qualifw2', 'intra_mitarbeiter_fwquali', 'id', [
                'constraint' => 'FK_intra_mitarbeiter_intra_mitarbeiter_fwquali',
                'delete'     => 'NO_ACTION',
                'update'     => 'CASCADE',
            ])
            ->addForeignKey('qualird', 'intra_mitarbeiter_rdquali', 'id', [
                'constraint' => 'FK_intra_mitarbeiter_intra_mitarbeiter_rdquali',
                'delete'     => 'NO_ACTION',
                'update'     => 'CASCADE',
            ])
            ->create();
    }
}
