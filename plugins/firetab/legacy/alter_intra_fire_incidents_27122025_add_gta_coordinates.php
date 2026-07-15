<?php
// Add GTA coordinates to fire incidents table
try {
    // Check if columns already exist
    $checkColumns = $pdo->query("SHOW COLUMNS FROM intra_fire_incidents LIKE 'location_%'");
    $existingColumns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);

    if (count($existingColumns) === 0) {
        $sql = <<<SQL
        ALTER TABLE `intra_fire_incidents`
        ADD COLUMN `location_x` DECIMAL(14,9) NULL COMMENT 'GTA X coordinate of incident location' AFTER `location`,
        ADD COLUMN `location_y` DECIMAL(14,9) NULL COMMENT 'GTA Y coordinate of incident location' AFTER `location_x`,
        ADD INDEX `idx_location_coords` (`location_x`, `location_y`);
        SQL;

        $pdo->exec($sql);
        echo "✓ Spalten 'location_x' und 'location_y' zur Tabelle 'intra_fire_incidents' hinzugefügt\n";
    } else {
        echo "ℹ Spalten 'location_x' und 'location_y' existieren bereits in 'intra_fire_incidents'\n";
    }
} catch (PDOException $e) {
    echo "❌ Fehler beim Hinzufügen der GTA-Koordinaten zu 'intra_fire_incidents': " . $e->getMessage() . "\n";
    throw $e;
}
