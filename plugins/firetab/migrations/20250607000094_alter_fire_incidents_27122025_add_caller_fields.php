<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Melder-Daten am Einsatz: Name und Kontakt (Telefon) der Person, die den
 * Einsatz gemeldet hat.
 */
class AlterFireIncidents27122025AddCallerFields extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_fire_incidents');
        if ($table->hasColumn('caller_name')) {
            return;
        }

        $table
            ->addColumn('caller_name', 'string', ['limit' => 255, 'null' => true, 'comment' => 'Name des Melders', 'after' => 'keyword'])
            ->addColumn('caller_contact', 'string', ['limit' => 255, 'null' => true, 'comment' => 'Kontakt des Melders (Telefon)', 'after' => 'caller_name'])
            ->update();
    }
}
