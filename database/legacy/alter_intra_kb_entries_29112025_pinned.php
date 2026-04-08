<?php
/**
 * Add is_pinned column to intra_kb_entries table
 */
try {
    // Check if column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `intra_kb_entries` LIKE 'is_pinned'");
    if ($stmt->rowCount() == 0) {
        $sql = "ALTER TABLE `intra_kb_entries` ADD COLUMN `is_pinned` TINYINT(1) NOT NULL DEFAULT 0 AFTER `mass_durchfuehrung`, ADD INDEX idx_pinned (is_pinned)";
        $pdo->exec($sql);
    }
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
