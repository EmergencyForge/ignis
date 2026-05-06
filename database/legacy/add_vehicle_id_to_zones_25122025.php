<?php
try {
    // Add vehicle_id column to intra_fire_incident_map_zones table
    $sql = <<<SQL
    ALTER TABLE `intra_fire_incident_map_zones`
    ADD COLUMN `vehicle_id` INT(11) NULL COMMENT 'Vehicle ID that created the zone' AFTER `created_by`,
    ADD INDEX `idx_vehicle` (`vehicle_id`),
    ADD CONSTRAINT `fk_map_zone_vehicle` 
        FOREIGN KEY (`vehicle_id`) 
        REFERENCES `intra_fahrzeuge` (`id`) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE;
    SQL;

    $pdo->exec($sql);
    echo "✓ Spalte 'vehicle_id' zur Tabelle 'intra_fire_incident_map_zones' hinzugefügt\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "ℹ Spalte 'vehicle_id' existiert bereits in 'intra_fire_incident_map_zones'\n";
    } else {
        echo "❌ Fehler beim Hinzufügen der Spalte 'vehicle_id': " . $e->getMessage() . "\n";
        throw $e;
    }
}
