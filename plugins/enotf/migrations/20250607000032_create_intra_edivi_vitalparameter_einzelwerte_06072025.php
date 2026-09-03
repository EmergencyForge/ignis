<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Einzelmesswerte der Vitalparameter je Einsatz (`enr`): Zeitpunkt, Parameter,
 * Wert und Einheit. Löschungen laufen ausschließlich als Soft-Delete über die
 * `geloescht`-Spalten; ein BEFORE-DELETE-Trigger blockiert hartes Löschen.
 */
class CreateIntraEdiviVitalparameterEinzelwerte06072025 extends AbstractMigration
{
    public function up(): void
    {
        $this->table('intra_edivi_vitalparameter_einzelwerte', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('enr',               'string',    ['limit' => 50, 'null' => false])
            ->addColumn('zeitpunkt',         'datetime',  ['null' => false])
            ->addColumn('parameter_name',    'string',    ['limit' => 100, 'null' => false])
            ->addColumn('parameter_wert',    'string',    ['limit' => 50, 'null' => false])
            ->addColumn('parameter_einheit', 'string',    ['limit' => 20, 'null' => false])
            ->addColumn('erstellt_von',      'string',    ['limit' => 100, 'null' => false])
            ->addColumn('erstellt_am',       'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('geloescht',         'boolean',   ['null' => true, 'default' => 0])
            ->addColumn('geloescht_am',      'timestamp', ['null' => true, 'default' => null])
            ->addColumn('geloescht_von',     'string',    ['limit' => 100, 'null' => true])
            ->addIndex(['enr'],            ['name' => 'idx_enr'])
            ->addIndex(['zeitpunkt'],      ['name' => 'idx_zeitpunkt'])
            ->addIndex(['parameter_name'], ['name' => 'idx_parameter_name'])
            ->addIndex(['geloescht'],      ['name' => 'idx_geloescht'])
            ->create();

        // Trigger kennt die Phinx-API nicht — raw SQL bleibt hier notwendig.
        $this->execute(<<<SQL
            CREATE TRIGGER `before_delete_vitalparameter_einzelwerte`
                BEFORE DELETE ON `intra_edivi_vitalparameter_einzelwerte`
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Direct deletion not allowed. Use soft delete instead.';
                END
            SQL);
    }

    public function down(): void
    {
        // DROP TABLE entfernt den zugehörigen Trigger automatisch mit.
        $this->table('intra_edivi_vitalparameter_einzelwerte')->drop()->save();
    }
}
