<?php
/**
 * Migration: Sonderrechte Anfahrt & Transport Felder
 *
 * Fügt zwei neue Spalten hinzu:
 * - sonderrechte_anfahrt: Tri-State (NULL=leer, 'nein', 'ja') - Pflichtfeld
 * - sonderrechte_transport: Tri-State (NULL=leer, 'nein', 'ja') - optional
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    // Prüfe ob Spalte bereits existiert
    $stmt = $pdo->prepare("SHOW COLUMNS FROM intra_edivi LIKE 'sonderrechte_anfahrt'");
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE intra_edivi ADD COLUMN sonderrechte_anfahrt VARCHAR(4) DEFAULT NULL AFTER ebesonderheiten");
        echo "Spalte 'sonderrechte_anfahrt' hinzugefügt.\n";
    } else {
        echo "Spalte 'sonderrechte_anfahrt' existiert bereits.\n";
    }

    $stmt = $pdo->prepare("SHOW COLUMNS FROM intra_edivi LIKE 'sonderrechte_transport'");
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE intra_edivi ADD COLUMN sonderrechte_transport VARCHAR(4) DEFAULT NULL AFTER sonderrechte_anfahrt");
        echo "Spalte 'sonderrechte_transport' hinzugefügt.\n";
    } else {
        echo "Spalte 'sonderrechte_transport' existiert bereits.\n";
    }

    echo "Migration erfolgreich abgeschlossen.\n";
} catch (PDOException $e) {
    echo "Fehler: " . $e->getMessage() . "\n";
}
