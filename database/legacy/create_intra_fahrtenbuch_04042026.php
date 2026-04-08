<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_fahrtenbuch` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `vehicle_id` INT NULL,
        `vehicle_identifier` VARCHAR(64) NOT NULL,
        `datum` DATE NOT NULL,
        `abfahrt` TIME NOT NULL,
        `ankunft` TIME NULL,
        `stationierungsort` VARCHAR(255) NOT NULL DEFAULT '',
        `kilometer` DECIMAL(8,1) NULL,
        `grund` TEXT NULL,
        `fahrttyp` VARCHAR(50) NOT NULL,
        `fahrer_name` VARCHAR(255) NOT NULL,
        `source` ENUM('enotf', 'firetab', 'admin') NOT NULL DEFAULT 'admin',
        `created_by` INT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`vehicle_id`) REFERENCES `intra_fahrzeuge`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`created_by`) REFERENCES `intra_users`(`id`) ON DELETE SET NULL,
        INDEX `idx_fahrtenbuch_vehicle` (`vehicle_id`),
        INDEX `idx_fahrtenbuch_datum` (`datum`),
        INDEX `idx_fahrtenbuch_fahrttyp` (`fahrttyp`),
        INDEX `idx_fahrtenbuch_fahrer` (`fahrer_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    SQL;
    $pdo->exec($sql);

    echo "✓ Fahrtenbuch Tabelle erstellt\n";
} catch (PDOException $e) {
    echo "Fehler: " . $e->getMessage() . "\n";
}
