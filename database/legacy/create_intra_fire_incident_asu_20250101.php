<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_fire_incident_asu` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `incident_id` INT NOT NULL,
        `supervisor` VARCHAR(255) NOT NULL,
        `mission_location` VARCHAR(255) NULL,
        `mission_date` DATE NULL,
        `timestamp` DATETIME NULL,
        `data` JSON NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        UNIQUE KEY unique_incident_supervisor (incident_id, supervisor),
        INDEX idx_incident (incident_id),

        FOREIGN KEY (incident_id) REFERENCES intra_fire_incidents(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    echo $e->getMessage();
}
