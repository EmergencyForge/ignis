<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_fire_incident_map_zones` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `incident_id` INT(11) NOT NULL,
        `name` VARCHAR(255) NOT NULL COMMENT 'Name of the zone',
        `description` TEXT NULL COMMENT 'Optional description of the zone',
        `points` TEXT NOT NULL COMMENT 'JSON array of polygon points',
        `color` VARCHAR(20) NOT NULL DEFAULT '#dc3545' COMMENT 'Color of the zone',
        `created_by` INT(11) NULL COMMENT 'User ID who created the zone',
        `vehicle_id` INT(11) NULL COMMENT 'Vehicle ID that created the zone',
        `operator_id` INT(11) NULL COMMENT 'Operator (person) on the vehicle who created the zone',
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        INDEX `idx_incident` (`incident_id`),
        INDEX `idx_created_by` (`created_by`),
        INDEX `idx_vehicle` (`vehicle_id`),
        INDEX `idx_operator` (`operator_id`),
        CONSTRAINT `fk_map_zone_incident` 
            FOREIGN KEY (`incident_id`) 
            REFERENCES `intra_fire_incidents` (`id`) 
            ON DELETE CASCADE 
            ON UPDATE CASCADE,
        CONSTRAINT `fk_map_zone_user` 
            FOREIGN KEY (`created_by`) 
            REFERENCES `intra_mitarbeiter` (`id`) 
            ON DELETE SET NULL 
            ON UPDATE CASCADE,
        CONSTRAINT `fk_map_zone_vehicle` 
            FOREIGN KEY (`vehicle_id`) 
            REFERENCES `intra_fahrzeuge` (`id`) 
            ON DELETE SET NULL 
            ON UPDATE CASCADE,
        CONSTRAINT `fk_map_zone_operator` 
            FOREIGN KEY (`operator_id`) 
            REFERENCES `intra_mitarbeiter` (`id`) 
            ON DELETE SET NULL 
            ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tactical map zones for fire incidents';
    SQL;

    $pdo->exec($sql);
    echo "✓ Tabelle 'intra_fire_incident_map_zones' erstellt oder bereits vorhanden\n";
} catch (PDOException $e) {
    echo "❌ Fehler beim Erstellen der Tabelle 'intra_fire_incident_map_zones': " . $e->getMessage() . "\n";
    throw $e;
}
