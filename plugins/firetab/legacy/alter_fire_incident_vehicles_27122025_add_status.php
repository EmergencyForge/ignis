<?php
try {
    // Füge current_status Spalte zu intra_fire_incident_vehicles hinzu
    $sql = <<<SQL
    ALTER TABLE intra_fire_incident_vehicles 
    ADD COLUMN current_status VARCHAR(10) NULL COMMENT 'Aktueller EMD Status (0-9, N, #, C)' AFTER radio_name,
    ADD COLUMN status_updated_at TIMESTAMP NULL COMMENT 'Zeitpunkt der letzten Status-Aktualisierung' AFTER current_status;
    SQL;

    $pdo->exec($sql);
    echo "Migration erfolgreich: current_status und status_updated_at Spalten hinzugefügt\n";
} catch (PDOException $e) {
    // Wenn die Spalten bereits existieren, ignoriere den Fehler
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Spalten existieren bereits\n";
    } else {
        echo "Fehler: " . $e->getMessage() . "\n";
    }
}
