<?php
/**
 * Database Schema Creation Script
 * Table: intra_edivi_hospital_availability
 * Purpose: Store real-time availability status for hospital departments
 * Created: 2026-01-21
 */

require_once __DIR__ . '/../config/database.php';

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS `intra_edivi_hospital_availability` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `department_id` INT NOT NULL,
        `status` ENUM('not_staffed', 'available', 'partially_available', 'full') NOT NULL DEFAULT 'not_staffed' COMMENT 'Grau=not_staffed, Grün=available, Gelb=partially_available, Rot=full',
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `updated_by` VARCHAR(255) NULL COMMENT 'User or system that updated the status',
        FOREIGN KEY (`department_id`) REFERENCES `intra_edivi_hospital_departments`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `unique_department_status` (`department_id`),
        INDEX `idx_department_id` (`department_id`),
        INDEX `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo "✓ Table 'intra_edivi_hospital_availability' created successfully.\n";

} catch (PDOException $e) {
    echo "✗ Error creating table 'intra_edivi_hospital_availability': " . $e->getMessage() . "\n";
    exit(1);
}
