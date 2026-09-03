<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Archivierung von Einsätzen: Flag, Zeitpunkt und archivierender User.
 */
class AlterFireIncidents27122025AddArchived extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_fire_incidents');
        if ($table->hasColumn('archived')) {
            return;
        }

        // Erst Spalten + Index, dann separat der Foreign Key: MariaDB legt
        // für den FK automatisch einen Index `archived_by` an, und der soll
        // im Schema NACH idx_archived stehen (wie im Original).
        $table
            ->addColumn('archived', 'boolean', ['default' => 0, 'null' => false, 'comment' => 'Ist der Einsatz archiviert?', 'after' => 'finalized_by'])
            ->addColumn('archived_at', 'timestamp', ['null' => true, 'comment' => 'Zeitpunkt der Archivierung', 'after' => 'archived'])
            ->addColumn('archived_by', 'integer', ['null' => true, 'comment' => 'User der archiviert hat', 'after' => 'archived_at'])
            ->addIndex(['archived'], ['name' => 'idx_archived'])
            ->update();

        $table
            ->addForeignKey('archived_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
            ->update();
    }
}
