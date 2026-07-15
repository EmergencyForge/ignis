<?php
/**
 * Database Schema Creation Script
 * Table: intra_edivi_hospital_access_codes
 * Purpose: Store access codes for hospitals to update their availability
 * Created: 2026-01-21
 */

// [phinx-wrapper] entfernt: require_once __DIR__ . '/../config/database.php';
try {
    $sql = "
    CREATE TABLE IF NOT EXISTS `intra_edivi_hospital_access_codes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `poi_id` INT NOT NULL,
        `code` VARCHAR(255) NOT NULL COMMENT 'Hashed access code/password',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`poi_id`) REFERENCES `intra_edivi_pois`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `unique_poi_code` (`poi_id`),
        INDEX `idx_poi_id` (`poi_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo "✓ Table 'intra_edivi_hospital_access_codes' created successfully.\n";

} catch (PDOException $e) {
    echo "✗ Error creating table 'intra_edivi_hospital_access_codes': " . $e->getMessage() . "\n";
    exit(1);
}
