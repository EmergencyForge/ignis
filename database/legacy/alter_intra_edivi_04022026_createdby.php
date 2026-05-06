<?php
/**
 * Fügt die Spalte 'createdby' zur intra_edivi Tabelle hinzu.
 *
 * Werte:
 * - 1 = Protokoll durch EMD-Sync (Leitstelle) erstellt
 * - 2 = Protokoll durch User manuell erstellt
 *
 * Default ist 1, damit bestehende Protokolle als Leitstellen-Protokolle gelten.
 */
try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi`
    ADD COLUMN `createdby` tinyint(1) NOT NULL DEFAULT 1
    AFTER `created_at`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        // Spalte existiert bereits, ignorieren
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}
