<?php
try {
    $sql = <<<SQL
    ALTER TABLE `intra_fire_incident_map_markers`
    ADD COLUMN `symbol` VARCHAR(100) NULL COMMENT 'Taktisches Zeichen: Symbol' AFTER `einheit`;
    SQL;

    $pdo->exec($sql);
    echo "✓ Spalte 'symbol' zu 'intra_fire_incident_map_markers' hinzugefügt\n";
} catch (PDOException $e) {
    // Check if column already exists
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "⏭️  Spalte 'symbol' existiert bereits\n";
    } else {
        echo "❌ Fehler beim Hinzufügen der Spalte 'symbol': " . $e->getMessage() . "\n";
        throw $e;
    }
}
