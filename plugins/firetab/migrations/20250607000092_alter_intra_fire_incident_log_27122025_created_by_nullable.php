<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Auto-generierter Wrapper für Legacy-Migration.
 *
 * Original-Datei: assets/database/alter_intra_fire_incident_log_27122025_created_by_nullable.php
 * Spiegelung:     database/legacy/alter_intra_fire_incident_log_27122025_created_by_nullable.php
 *
 * Diese Migration bindet die Legacy-Datei ein, die selbst raw SQL gegen $pdo
 * ausführt. So bleibt das ursprüngliche SQL byte-identisch erhalten und kann
 * später inkrementell auf native Phinx-API umgeschrieben werden.
 */
class AlterIntraFireIncidentLog27122025CreatedByNullable extends AbstractMigration
{
    public function change(): void
    {
        $pdo = $this->getAdapter()->getConnection();
        $projectRoot = dirname(__DIR__, 2);
        $__autoMigrator = true; // signalisiert: in eingebettetem Kontext
        require __DIR__ . '/../legacy/alter_intra_fire_incident_log_27122025_created_by_nullable.php';
    }
}
