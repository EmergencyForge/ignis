<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

/**
 * Fügt Symptome-Felder zur intra_edivi Tabelle hinzu.
 *
 * - symptombeginn_datum: Datum des Symptombeginns
 * - symptombeginn_zeit: Uhrzeit des Symptombeginns (HH:MM)
 * - symptombeginn_geschaetzt: Flag ob Zeitpunkt geschätzt
 * - symptombeginn_nf: Flag ob nicht feststellbar
 * - naca_initial: NACA-Score initial (0-7)
 * - naca_uebergabe: NACA-Score bei Übergabe (0-7)
 */
class AlterIntraEdivi09022026Symptome extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_edivi');

        $columns = [
            ['symptombeginn_datum',      'date',    ['null' => true, 'after' => 'anmerkungen']],
            ['symptombeginn_zeit',       'string',  ['limit' => 5, 'null' => true, 'after' => 'symptombeginn_datum']],
            ['symptombeginn_geschaetzt', 'boolean', ['null' => false, 'default' => 0, 'after' => 'symptombeginn_zeit']],
            ['symptombeginn_nf',         'boolean', ['null' => false, 'default' => 0, 'after' => 'symptombeginn_geschaetzt']],
            ['naca_initial',             'integer', ['limit' => MysqlAdapter::INT_TINY, 'null' => true, 'after' => 'symptombeginn_nf']],
            ['naca_uebergabe',           'integer', ['limit' => MysqlAdapter::INT_TINY, 'null' => true, 'after' => 'naca_initial']],
        ];

        foreach ($columns as [$name, $type, $options]) {
            if (!$table->hasColumn($name)) {
                $table->addColumn($name, $type, $options);
            }
        }

        $table->update();
    }
}
