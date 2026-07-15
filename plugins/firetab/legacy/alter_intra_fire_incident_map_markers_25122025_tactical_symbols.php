<?php
try {
    $sql = <<<SQL
    ALTER TABLE `intra_fire_incident_map_markers`
    ADD COLUMN `grundzeichen` VARCHAR(100) NULL COMMENT 'Taktisches Zeichen: Grundzeichen' AFTER `marker_type`,
    ADD COLUMN `organisation` VARCHAR(100) NULL COMMENT 'Taktisches Zeichen: Organisation' AFTER `grundzeichen`,
    ADD COLUMN `fachaufgabe` VARCHAR(100) NULL COMMENT 'Taktisches Zeichen: Fachaufgabe' AFTER `organisation`,
    ADD COLUMN `einheit` VARCHAR(100) NULL COMMENT 'Taktisches Zeichen: Einheit' AFTER `fachaufgabe`;
    SQL;

    $pdo->exec($sql);
    echo "✓ Spalten für taktische Zeichen zu 'intra_fire_incident_map_markers' hinzugefügt\n";
} catch (PDOException $e) {
    // Check if columns already exist
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "⏭️  Spalten für taktische Zeichen existieren bereits\n";
    } else {
        echo "❌ Fehler beim Hinzufügen der Spalten für taktische Zeichen: " . $e->getMessage() . "\n";
        throw $e;
    }
}
