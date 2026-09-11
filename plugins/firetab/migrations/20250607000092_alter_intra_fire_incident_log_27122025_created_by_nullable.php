<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * created_by im Einsatz-Log nullable machen, damit System-Einträge ohne
 * User-Bezug möglich sind.
 */
class AlterIntraFireIncidentLog27122025CreatedByNullable extends AbstractMigration
{
    public function up(): void
    {
        $this->table('intra_fire_incident_log')
            ->changeColumn('created_by', 'integer', ['null' => true])
            ->update();
    }

    public function down(): void
    {
        // Die Spalte war schon vor dieser Migration nullable (seit
        // 20250607000080) — es gibt nichts zurückzudrehen.
    }
}
