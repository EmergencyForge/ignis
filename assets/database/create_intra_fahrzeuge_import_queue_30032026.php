<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_fahrzeuge_import_queue` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `emd_vehicle_id` INT NULL,
        `name` VARCHAR(255) NOT NULL,
        `identifier` VARCHAR(255) NULL,
        `veh_type` VARCHAR(255) NULL,
        `rd_type` TINYINT NOT NULL DEFAULT 0,
        `department` VARCHAR(255) NULL,
        `valuelong` VARCHAR(255) NULL,
        `job` VARCHAR(255) NULL,
        `image` VARCHAR(100) NULL,
        `funkkanal` VARCHAR(50) NULL,
        `raw_data` JSON NULL,
        `status` ENUM('pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `processed_at` DATETIME NULL,
        `processed_by` INT NULL,

        INDEX idx_status (status),
        FOREIGN KEY (processed_by) REFERENCES intra_users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    echo $e->getMessage();
}
