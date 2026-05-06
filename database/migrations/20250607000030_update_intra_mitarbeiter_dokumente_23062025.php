<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Auto-generierter Wrapper für Legacy-Migration.
 *
 * Original-Datei: assets/database/update_intra_mitarbeiter_dokumente_23062025.php
 * Spiegelung:     database/legacy/update_intra_mitarbeiter_dokumente_23062025.php
 *
 * Diese Migration bindet die Legacy-Datei ein, die selbst raw SQL gegen $pdo
 * ausführt. So bleibt das ursprüngliche SQL byte-identisch erhalten und kann
 * später inkrementell auf native Phinx-API umgeschrieben werden.
 */
class UpdateIntraMitarbeiterDokumente23062025 extends AbstractMigration
{
    public function change(): void
    {
        $pdo = $this->getAdapter()->getConnection();
        $projectRoot = dirname(__DIR__, 2);
        $__autoMigrator = true; // signalisiert: in eingebettetem Kontext
        require __DIR__ . '/../legacy/update_intra_mitarbeiter_dokumente_23062025.php';
    }
}
