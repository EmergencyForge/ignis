<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_federation_links` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `instance_id` varchar(36) NOT NULL COMMENT 'UUID of remote instance',
        `instance_name` varchar(100) NOT NULL COMMENT 'Display name of remote instance',
        `instance_url` varchar(255) NOT NULL COMMENT 'Base URL of remote instance',
        `api_key_outgoing` varchar(64) NOT NULL COMMENT 'Key we send when fetching from them',
        `api_key_incoming` varchar(64) NOT NULL COMMENT 'Key they must send to us',
        `consume_personnel` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Pull their personnel',
        `consume_enotf` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Pull their eNOTF protocols',
        `consume_fire` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Pull their fire incidents',
        `provide_personnel` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Expose our personnel to them',
        `provide_enotf` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Expose our eNOTF protocols to them',
        `provide_fire` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Expose our fire incidents to them',
        `sync_interval_minutes` int(11) NOT NULL DEFAULT 15,
        `last_sync_at` datetime DEFAULT NULL,
        `last_sync_status` enum('success','error','pending') NOT NULL DEFAULT 'pending',
        `last_sync_error` text DEFAULT NULL,
        `is_active` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_instance_id` (`instance_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    echo $e->getMessage();
}
