<?php
/**
 * Database Alter Script
 * Table: intra_edivi_hospital_access_codes
 * Purpose: Change code storage from hashed to plaintext and clear existing codes
 * Created: 2026-01-21
 */

// [phinx-wrapper] entfernt: require_once __DIR__ . '/../config/database.php';
try {
    // First, clear all existing codes since they are hashed and cannot be recovered
    $pdo->exec("TRUNCATE TABLE `intra_edivi_hospital_access_codes`");

    echo "✓ Existing access codes cleared (they were hashed and need to be regenerated).\n";
    echo "  Please regenerate access codes for all hospitals.\n";

} catch (PDOException $e) {
    echo "✗ Error altering table 'intra_edivi_hospital_access_codes': " . $e->getMessage() . "\n";
    exit(1);
}
