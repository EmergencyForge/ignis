<?php
/**
 * Add metadata column to intra_mitarbeiter_log table
 * This supports structured data storage for log entries
 */
try {
    // Check if column already exists
    $checkStmt = $pdo->query("SHOW COLUMNS FROM intra_mitarbeiter_log LIKE 'metadata'");
    $columnExists = $checkStmt->rowCount() > 0;

    if (!$columnExists) {
        $sql = "ALTER TABLE `intra_mitarbeiter_log` 
                ADD COLUMN `metadata` TEXT NULL DEFAULT NULL 
                AFTER `paneluser`";
        
        $pdo->exec($sql);
        echo "Added metadata column to intra_mitarbeiter_log table.\n";
    } else {
        echo "Column metadata already exists in intra_mitarbeiter_log table.\n";
    }
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo "Error: " . $message . "\n";
}
