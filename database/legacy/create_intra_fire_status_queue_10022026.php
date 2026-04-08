<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_fire_status_queue` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `vehicle_id` INT NOT NULL,
        `vehicle_name` VARCHAR(255) NOT NULL,
        `incident_number` VARCHAR(50) NULL,
        `new_status` VARCHAR(10) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `delivered` TINYINT(1) NOT NULL DEFAULT 0,

        INDEX idx_delivered (delivered),
        INDEX idx_vehicle (vehicle_id),

        FOREIGN KEY (vehicle_id) REFERENCES intra_fahrzeuge(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    echo $e->getMessage();
}
