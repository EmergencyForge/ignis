<?php
try {
    // Füge caller_name und caller_contact Felder zu intra_fire_incidents hinzu
    $sql = <<<SQL
    ALTER TABLE intra_fire_incidents 
    ADD COLUMN caller_name VARCHAR(255) NULL COMMENT 'Name des Melders' AFTER keyword,
    ADD COLUMN caller_contact VARCHAR(255) NULL COMMENT 'Kontakt des Melders (Telefon)' AFTER caller_name;
    SQL;

    $pdo->exec($sql);
    echo "Migration erfolgreich: caller_name und caller_contact Spalten hinzugefügt\n";
} catch (PDOException $e) {
    // Wenn die Spalten bereits existieren, ignoriere den Fehler
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Spalten existieren bereits\n";
    } else {
        echo "Fehler: " . $e->getMessage() . "\n";
    }
}
