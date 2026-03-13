<?php

/**
 * Add allowed_jobs column to vehicles table for job-based filtering
 * Date: 2026-03-13
 */

if (!isset($pdo)) {
    die('Database connection not available');
}

try {
    $pdo->exec("
        ALTER TABLE intra_fahrzeuge
        ADD COLUMN `allowed_jobs` VARCHAR(500) DEFAULT NULL COMMENT 'Kommagetrennte Job-Namen die dieses Fahrzeug sehen duerfen. NULL = alle.' AFTER `rd_type`
    ");
    echo "✓ Spalte 'allowed_jobs' zu 'intra_fahrzeuge' hinzugefügt\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "⏭️  Spalte 'allowed_jobs' existiert bereits\n";
    } else {
        throw $e;
    }
}
