<?php
/**
 * Migration: Fuegt is_archived Spalte und Index zu intra_mitarbeiter_dokumente hinzu.
 * Ermoeglicht Archivierung von Dokumenten (statt nur Loeschen).
 */
try {
    // Pruefe ob Spalte bereits existiert
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'intra_mitarbeiter_dokumente'
        AND COLUMN_NAME = 'is_archived'
    ");
    $stmt->execute();

    if (!$stmt->fetchColumn()) {
        $pdo->exec("
            ALTER TABLE `intra_mitarbeiter_dokumente`
            ADD COLUMN `is_archived` TINYINT(1) NOT NULL DEFAULT 0 AFTER `timestamp`,
            ADD INDEX `idx_dokumente_archived` (`is_archived`)
        ");
    }
} catch (PDOException $e) {
    // Spalte existiert bereits oder anderer Fehler
    if (strpos($e->getMessage(), 'Duplicate column') === false) {
        echo $e->getMessage();
    }
}
