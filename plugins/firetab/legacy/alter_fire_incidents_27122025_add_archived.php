<?php
try {
    // Füge archived Spalte zu intra_fire_incidents hinzu
    $sql = <<<SQL
    ALTER TABLE intra_fire_incidents 
    ADD COLUMN archived TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Ist der Einsatz archiviert?' AFTER finalized_by,
    ADD COLUMN archived_at TIMESTAMP NULL COMMENT 'Zeitpunkt der Archivierung' AFTER archived,
    ADD COLUMN archived_by INT NULL COMMENT 'User der archiviert hat' AFTER archived_at,
    ADD INDEX idx_archived (archived),
    ADD FOREIGN KEY (archived_by) REFERENCES intra_users(id) ON DELETE SET NULL;
    SQL;

    $pdo->exec($sql);
    echo "Migration erfolgreich: archived, archived_at und archived_by Spalten hinzugefügt\n";
} catch (PDOException $e) {
    // Wenn die Spalten bereits existieren, ignoriere den Fehler
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Spalten existieren bereits\n";
    } else {
        echo "Fehler: " . $e->getMessage() . "\n";
    }
}
