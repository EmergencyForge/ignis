<?php
try {
    // Personnel cache
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `intra_federation_cache_personnel` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `source_instance_id` varchar(36) NOT NULL,
            `remote_id` int(11) NOT NULL,
            `fullname` varchar(100) DEFAULT NULL,
            `dienstnr` varchar(20) DEFAULT NULL,
            `dienstgrad_name` varchar(50) DEFAULT NULL,
            `dienstgrad_badge` varchar(20) DEFAULT NULL,
            `quali_rd` varchar(50) DEFAULT NULL,
            `quali_fw` varchar(50) DEFAULT NULL,
            `quali_fd` varchar(50) DEFAULT NULL,
            `cached_data` json DEFAULT NULL COMMENT 'Full record as fallback',
            `cached_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_source_remote` (`source_instance_id`, `remote_id`),
            KEY `idx_source` (`source_instance_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // eNOTF cache
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `intra_federation_cache_enotf` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `source_instance_id` varchar(36) NOT NULL,
            `remote_id` int(11) NOT NULL,
            `cached_data` json NOT NULL,
            `protocol_date` datetime DEFAULT NULL,
            `cached_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_source_remote` (`source_instance_id`, `remote_id`),
            KEY `idx_source` (`source_instance_id`),
            KEY `idx_protocol_date` (`protocol_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Fire incidents cache
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `intra_federation_cache_fire` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `source_instance_id` varchar(36) NOT NULL,
            `remote_id` int(11) NOT NULL,
            `incident_number` varchar(20) DEFAULT NULL,
            `cached_data` json NOT NULL,
            `incident_date` datetime DEFAULT NULL,
            `cached_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_source_remote` (`source_instance_id`, `remote_id`),
            KEY `idx_source` (`source_instance_id`),
            KEY `idx_incident_date` (`incident_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Sync log
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `intra_federation_sync_log` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `link_id` int(11) NOT NULL,
            `sync_type` enum('personnel','enotf','fire') NOT NULL,
            `status` enum('success','error') NOT NULL,
            `records_synced` int(11) NOT NULL DEFAULT 0,
            `duration_ms` int(11) DEFAULT NULL,
            `error_message` text DEFAULT NULL,
            `synced_at` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_link_synced` (`link_id`, `synced_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (PDOException $e) {
    echo $e->getMessage();
}
