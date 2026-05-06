<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_fire_incident_vehicles` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `incident_id` INT NOT NULL,
        `vehicle_id` INT NULL,
        `vehicle_name` VARCHAR(255) NULL,
        `vehicle_identifier` VARCHAR(255) NULL,
        `from_other_org` TINYINT(1) NOT NULL DEFAULT 0,
        `radio_name` VARCHAR(255) NULL,

        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `created_by` INT NULL,

        INDEX idx_incident (incident_id),
        INDEX idx_vehicle (vehicle_id),

        FOREIGN KEY (incident_id) REFERENCES intra_fire_incidents(id) ON DELETE CASCADE,
        FOREIGN KEY (vehicle_id) REFERENCES intra_fahrzeuge(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES intra_users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    echo $e->getMessage();
}
