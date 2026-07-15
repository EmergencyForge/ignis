<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_fire_incident_map_markers` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `incident_id` INT(11) NOT NULL,
        `marker_type` VARCHAR(50) NOT NULL COMMENT 'Type of marker: fire, vehicle, person, water, danger, command, staging, other',
        `pos_x` DECIMAL(5,2) NOT NULL COMMENT 'X position as percentage (0-100)',
        `pos_y` DECIMAL(5,2) NOT NULL COMMENT 'Y position as percentage (0-100)',
        `description` TEXT NULL COMMENT 'Optional description of the marker',
        `created_by` INT(11) NULL COMMENT 'User ID who created the marker',
        `vehicle_id` INT(11) NULL COMMENT 'Vehicle ID that created the marker',
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        INDEX `idx_incident` (`incident_id`),
        INDEX `idx_created_by` (`created_by`),
        INDEX `idx_vehicle` (`vehicle_id`),
        INDEX `idx_marker_type` (`marker_type`),
        CONSTRAINT `fk_map_marker_incident` 
            FOREIGN KEY (`incident_id`) 
            REFERENCES `intra_fire_incidents` (`id`) 
            ON DELETE CASCADE 
            ON UPDATE CASCADE,
        CONSTRAINT `fk_map_marker_user` 
            FOREIGN KEY (`created_by`) 
            REFERENCES `intra_mitarbeiter` (`id`) 
            ON DELETE SET NULL 
            ON UPDATE CASCADE,
        CONSTRAINT `fk_map_marker_vehicle` 
            FOREIGN KEY (`vehicle_id`) 
            REFERENCES `intra_fahrzeuge` (`id`) 
            ON DELETE SET NULL 
            ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tactical map markers for fire incidents';
    SQL;

    $pdo->exec($sql);
    echo "✓ Tabelle 'intra_fire_incident_map_markers' erstellt oder bereits vorhanden\n";
} catch (PDOException $e) {
    echo "❌ Fehler beim Erstellen der Tabelle 'intra_fire_incident_map_markers': " . $e->getMessage() . "\n";
    throw $e;
}
