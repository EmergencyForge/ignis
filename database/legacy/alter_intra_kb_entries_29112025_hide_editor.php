<?php
/**
 * Add hide_editor column to intra_kb_entries table
 * Allows per-article anonymization of editor names (only admins can toggle)
 */
try {
    // Check if column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `intra_kb_entries` LIKE 'hide_editor'");
    if ($stmt->rowCount() == 0) {
        $sql = "ALTER TABLE `intra_kb_entries` ADD COLUMN `hide_editor` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_pinned`";
        $pdo->exec($sql);
    }
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
