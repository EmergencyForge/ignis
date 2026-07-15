<?php
/**
 * Database Schema Creation Script
 * Table: intra_edivi_hospital_departments
 * Purpose: Store hospital department definitions for POIs
 * Created: 2026-01-21
 */

// [phinx-wrapper] entfernt: require_once __DIR__ . '/../config/database.php';
try {
    $sql = "
    CREATE TABLE IF NOT EXISTS `intra_edivi_hospital_departments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `poi_id` INT NOT NULL,
        `name` VARCHAR(255) NOT NULL COMMENT 'Department name (e.g., ZNA/INA, Schockraum, Intensivstation)',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`poi_id`) REFERENCES `intra_edivi_pois`(`id`) ON DELETE CASCADE,
        INDEX `idx_poi_id` (`poi_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo "✓ Table 'intra_edivi_hospital_departments' created successfully.\n";

} catch (PDOException $e) {
    echo "✗ Error creating table 'intra_edivi_hospital_departments': " . $e->getMessage() . "\n";
    exit(1);
}
