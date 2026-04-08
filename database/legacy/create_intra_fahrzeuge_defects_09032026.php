<?php
try {
    // Defekt-Meldungen
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_fahrzeuge_defects` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `vehicle_id` INT NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `category` VARCHAR(50) NOT NULL DEFAULT 'sonstiges',
        `vehicle_operable` TINYINT(1) NOT NULL DEFAULT 1,
        `status` ENUM('open', 'in_progress', 'deferred', 'resolved') NOT NULL DEFAULT 'open',
        `reported_by` INT NOT NULL,
        `assigned_to` INT DEFAULT NULL,
        `resolved_by` INT DEFAULT NULL,
        `resolved_at` DATETIME DEFAULT NULL,
        `resolution_note` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`vehicle_id`) REFERENCES `intra_fahrzeuge`(`id`) ON DELETE CASCADE,
        INDEX `idx_defects_vehicle` (`vehicle_id`),
        INDEX `idx_defects_status` (`status`),
        INDEX `idx_defects_category` (`category`),
        INDEX `idx_defects_operable` (`vehicle_operable`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    SQL;
    $pdo->exec($sql);

    // Status-Verlauf (wer hat wann was geändert)
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_fahrzeuge_defect_log` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `defect_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `action` VARCHAR(50) NOT NULL,
        `details` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`defect_id`) REFERENCES `intra_fahrzeuge_defects`(`id`) ON DELETE CASCADE,
        INDEX `idx_defect_log_defect` (`defect_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    SQL;
    $pdo->exec($sql);

    echo "✓ Fahrzeug-Defekte + Log Tabellen erstellt\n";
} catch (PDOException $e) {
    echo "Fehler: " . $e->getMessage() . "\n";
}
