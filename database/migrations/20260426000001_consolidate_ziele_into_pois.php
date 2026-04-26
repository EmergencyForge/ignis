<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Konsolidiert die Legacy-Tabelle `intra_edivi_ziele` in `intra_edivi_pois`.
 *
 * Vorher: zwei parallele Datenmodelle für Krankenhäuser/Ziele — `intra_edivi
 * _ziele` (alt, nur identifier+name+priority+transport+active) und `intra
 * _edivi_pois` (neu, mit Adresse, Departments, Access-Codes). Das führte
 * dazu, dass z. B. abschluss/freigabe.php auf der alten Tabelle suchte,
 * während Voranmeldungen längst über POIs liefen.
 *
 * Diese Migration:
 *   1. Fügt `legacy_identifier` (UNIQUE, NULL erlaubt) zu pois hinzu.
 *   2. Kopiert jede ziele-Row, deren identifier noch nicht als
 *      legacy_identifier in pois existiert, mit minimalem Adress-Stub
 *      (ort='', strasse=NULL).
 *
 * Die alte Tabelle wird BEWUSST nicht gedroppt — produktionsseitiger
 * Verify ist Voraussetzung. Ein späterer Migration-Schritt entfernt sie.
 */
final class ConsolidateZieleIntoPois extends AbstractMigration
{
    public function change(): void
    {
        $pdo = $this->getAdapter()->getConnection();

        // Legacy-Identifier-Spalte anlegen, falls noch nicht da.
        $columns = $pdo->query("SHOW COLUMNS FROM intra_edivi_pois LIKE 'legacy_identifier'")->fetchAll();
        if (!$columns) {
            $pdo->exec(
                "ALTER TABLE intra_edivi_pois
                 ADD COLUMN legacy_identifier VARCHAR(255) NULL DEFAULT NULL AFTER name,
                 ADD UNIQUE KEY idx_legacy_identifier (legacy_identifier)"
            );
        }

        // Falls die alte Tabelle nicht (mehr) existiert, ist der Rest no-op.
        $tableExists = $pdo->query("SHOW TABLES LIKE 'intra_edivi_ziele'")->fetchAll();
        if (!$tableExists) {
            return;
        }

        // Ziele kopieren. INSERT IGNORE über UNIQUE(legacy_identifier) sorgt
        // dafür, dass die Migration idempotent ist.
        $pdo->exec(
            "INSERT IGNORE INTO intra_edivi_pois
                (name, legacy_identifier, ort, active, created_at)
             SELECT name, identifier, '' AS ort, active, created_at
             FROM intra_edivi_ziele"
        );
    }
}
