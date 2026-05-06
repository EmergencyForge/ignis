<?php
/**
 * Migration: Tabelle für ausgeblendete Announcements erstellen
 */

if (!isset($pdo)) {
    throw new Exception('PDO connection required');
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS intra_global_announcements_dismissed (
        id INT AUTO_INCREMENT PRIMARY KEY,
        announcement_id VARCHAR(64) NOT NULL,
        user_id INT NOT NULL,
        dismissed_at DATETIME NOT NULL,
        
        UNIQUE KEY unique_user_announcement (announcement_id, user_id),
        INDEX idx_user (user_id),
        INDEX idx_dismissed (dismissed_at),
        
        FOREIGN KEY (user_id) REFERENCES intra_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "Tabelle intra_global_announcements_dismissed erstellt.\n";
