<?php
try {
    // Add source column to distinguish dispatch vs local sitreps
    $pdo->exec("ALTER TABLE `intra_fire_incident_sitreps` ADD COLUMN `source` VARCHAR(50) NULL DEFAULT NULL AFTER `created_by`");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') === false) {
        echo $e->getMessage();
    }
}

try {
    // Add synced flag to track which local sitreps have been sent back to dispatch
    $pdo->exec("ALTER TABLE `intra_fire_incident_sitreps` ADD COLUMN `synced` TINYINT(1) NOT NULL DEFAULT 0 AFTER `source`");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') === false) {
        echo $e->getMessage();
    }
}
