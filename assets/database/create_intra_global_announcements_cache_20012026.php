<?php
/**
 * Migration: Tabelle für globale Announcements Cache erstellen
 */

if (!isset($pdo)) {
    throw new Exception('PDO connection required');
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS intra_global_announcements_cache (
        id INT AUTO_INCREMENT PRIMARY KEY,
        announcement_id VARCHAR(64) NOT NULL,
        type ENUM('info', 'warning', 'critical', 'success', 'update') DEFAULT 'info',
        title VARCHAR(255) NOT NULL,
        message TEXT,
        link VARCHAR(512),
        priority INT DEFAULT 0,
        valid_from DATETIME NOT NULL,
        valid_until DATETIME,
        created_at DATETIME,
        fetched_at DATETIME NOT NULL,
        
        UNIQUE KEY unique_announcement (announcement_id),
        INDEX idx_type (type),
        INDEX idx_priority (priority),
        INDEX idx_validity (valid_from, valid_until),
        INDEX idx_fetched (fetched_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "Tabelle intra_global_announcements_cache erstellt.\n";
