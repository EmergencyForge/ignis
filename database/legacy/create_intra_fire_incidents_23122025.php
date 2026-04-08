<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_fire_incidents` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `incident_number` VARCHAR(50) NULL,
        `location` VARCHAR(255) NOT NULL,
        `keyword` VARCHAR(255) NOT NULL,
        `started_at` DATETIME NOT NULL,
        `leader_id` INT NULL,

        `owner_type` ENUM('geschaedigter','eigentümer','halter') NULL,
        `owner_name` VARCHAR(255) NULL,
        `owner_contact` VARCHAR(255) NULL,

        `status` TINYINT(3) NOT NULL DEFAULT 0,
        `finalized` TINYINT(1) NOT NULL DEFAULT 0,
        `finalized_at` TIMESTAMP NULL,
        `finalized_by` INT NULL,

        `notes` TEXT NULL,

        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `created_by` INT NULL,
        `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        `updated_by` INT NULL,

        UNIQUE KEY uniq_incident_number (incident_number),
        INDEX idx_started_at (started_at),
        INDEX idx_status (status),
        INDEX idx_leader (leader_id),

        FOREIGN KEY (leader_id) REFERENCES intra_mitarbeiter(id) ON DELETE SET NULL,
        FOREIGN KEY (finalized_by) REFERENCES intra_users(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES intra_users(id) ON DELETE SET NULL,
        FOREIGN KEY (updated_by) REFERENCES intra_users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    echo $e->getMessage();
}
