<?php
try {
    // Ändere created_by Spalte zu NULL erlaubt für System-Einträge
    $sql = <<<SQL
    ALTER TABLE intra_fire_incident_log 
    MODIFY COLUMN created_by INT NULL;
    SQL;

    $pdo->exec($sql);
    echo "Migration erfolgreich: created_by Spalte ist jetzt nullable für System-Einträge\n";
} catch (PDOException $e) {
    // Wenn die Spalte bereits nullable ist, ignoriere den Fehler
    if (strpos($e->getMessage(), 'Duplicate column name') === false) {
        echo "Hinweis: " . $e->getMessage() . "\n";
    }
}
