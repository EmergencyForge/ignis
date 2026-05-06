<?php
try {
    // Add operator_id column to intra_fire_incident_map_zones table
    $sql = <<<SQL
    ALTER TABLE `intra_fire_incident_map_zones`
    ADD COLUMN `operator_id` INT(11) NULL COMMENT 'Operator (person) on the vehicle who created the zone' AFTER `vehicle_id`,
    ADD INDEX `idx_operator` (`operator_id`),
    ADD CONSTRAINT `fk_map_zone_operator` 
        FOREIGN KEY (`operator_id`) 
        REFERENCES `intra_mitarbeiter` (`id`) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE;
    SQL;

    $pdo->exec($sql);
    echo "✓ Spalte 'operator_id' zur Tabelle 'intra_fire_incident_map_zones' hinzugefügt\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "ℹ Spalte 'operator_id' existiert bereits in 'intra_fire_incident_map_zones'\n";
    } else {
        echo "❌ Fehler beim Hinzufügen der Spalte 'operator_id': " . $e->getMessage() . "\n";
        throw $e;
    }
}
