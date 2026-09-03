<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fügt current_status, status_updated_at und status_source zu intra_fahrzeuge
 * hinzu. Damit kann der Fahrzeugstatus auch ohne aktiven Einsatz
 * (no_dispatch) gespeichert werden.
 */
class AlterIntraFahrzeuge10022026Status extends AbstractMigration
{
    public function change(): void
    {
        $columns = [
            ['current_status',    'string',   ['limit' => 10, 'after' => 'rd_type']],
            ['status_updated_at', 'datetime', ['after' => 'current_status']],
            ['status_source',     'string',   ['limit' => 50, 'after' => 'status_updated_at']],
        ];

        $table = $this->table('intra_fahrzeuge');
        $changed = false;

        foreach ($columns as [$name, $type, $options]) {
            if ($table->hasColumn($name)) {
                continue;
            }
            $table->addColumn($name, $type, $options + ['null' => true, 'default' => null]);
            $changed = true;
        }

        if ($changed) {
            $table->update();
        }
    }
}
