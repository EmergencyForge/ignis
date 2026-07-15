<?php
/**
 * Database Schema Alteration Script
 * Table: intra_edivi_hospital_departments
 * Purpose: Add sort_order column for custom department ordering
 * Created: 2026-01-21
 */

// [phinx-wrapper] entfernt: require_once __DIR__ . '/../config/database.php';
try {
    // Check if column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM intra_edivi_hospital_departments LIKE 'sort_order'");

    if ($stmt->rowCount() == 0) {
        $sql = "
        ALTER TABLE `intra_edivi_hospital_departments`
        ADD COLUMN `sort_order` INT NOT NULL DEFAULT 999 COMMENT 'Custom sort order (lower = higher priority)' AFTER `name`,
        ADD INDEX `idx_sort_order` (`sort_order`)
        ";

        $pdo->exec($sql);
        echo "✓ Column 'sort_order' added to 'intra_edivi_hospital_departments' table.\n";
    } else {
        echo "→ Column 'sort_order' already exists in 'intra_edivi_hospital_departments' table.\n";
    }

} catch (PDOException $e) {
    // If column already exists, don't fail - this is expected on re-runs
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "→ Column 'sort_order' already exists in 'intra_edivi_hospital_departments' table.\n";
    } else {
        echo "✗ Error altering table 'intra_edivi_hospital_departments': " . $e->getMessage() . "\n";
        exit(1);
    }
}
