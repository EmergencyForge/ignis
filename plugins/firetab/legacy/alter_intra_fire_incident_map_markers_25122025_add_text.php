<?php
try {
    $sql = <<<SQL
    ALTER TABLE `intra_fire_incident_map_markers`
    ADD COLUMN `text` VARCHAR(100) NULL COMMENT 'Taktisches Zeichen: Text-Beschriftung' AFTER `symbol`;
    SQL;

    $pdo->exec($sql);
    echo "✓ Spalte 'text' zu 'intra_fire_incident_map_markers' hinzugefügt\n";
} catch (PDOException $e) {
    // Check if column already exists
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "⏭️  Spalte 'text' existiert bereits\n";
    } else {
        echo "❌ Fehler beim Hinzufügen der Spalte 'text': " . $e->getMessage() . "\n";
        throw $e;
    }
}

// Add name column
try {
    $sql = <<<SQL
    ALTER TABLE `intra_fire_incident_map_markers`
    ADD COLUMN `name` VARCHAR(100) NULL COMMENT 'Taktisches Zeichen: Name' AFTER `text`;
    SQL;

    $pdo->exec($sql);
    echo "✓ Spalte 'name' zu 'intra_fire_incident_map_markers' hinzugefügt\n";
} catch (PDOException $e) {
    // Check if column already exists
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "⏭️  Spalte 'name' existiert bereits\n";
    } else {
        echo "❌ Fehler beim Hinzufügen der Spalte 'name': " . $e->getMessage() . "\n";
        throw $e;
    }
}

// Add typ column
try {
    $sql = <<<SQL
    ALTER TABLE `intra_fire_incident_map_markers`
    ADD COLUMN `typ` VARCHAR(100) NULL COMMENT 'Taktisches Zeichen: Typ (einsatz, geplant, etc.)' AFTER `name`;
    SQL;

    $pdo->exec($sql);
    echo "✓ Spalte 'typ' zu 'intra_fire_incident_map_markers' hinzugefügt\n";
} catch (PDOException $e) {
    // Check if column already exists
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "⏭️  Spalte 'typ' existiert bereits\n";
    } else {
        echo "❌ Fehler beim Hinzufügen der Spalte 'typ': " . $e->getMessage() . "\n";
        throw $e;
    }
}
