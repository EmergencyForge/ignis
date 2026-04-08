<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_config` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `config_key` varchar(100) NOT NULL UNIQUE COMMENT 'Configuration key (e.g., SYSTEM_NAME)',
        `config_value` text DEFAULT NULL COMMENT 'Configuration value',
        `config_type` varchar(50) NOT NULL DEFAULT 'string' COMMENT 'Type: string, boolean, integer, color, url',
        `category` varchar(50) NOT NULL COMMENT 'Category: basis, server, rp, funktionen',
        `description` varchar(255) DEFAULT NULL COMMENT 'Human-readable description',
        `is_editable` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0=read-only, 1=editable',
        `display_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Order for display in UI',
        `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        `updated_by` int(11) DEFAULT NULL COMMENT 'User ID who last updated',
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_config_key` (`config_key`),
        KEY `idx_category` (`category`),
        KEY `FK_intra_config_user` (`updated_by`),
        CONSTRAINT `FK_intra_config_user` FOREIGN KEY (`updated_by`) REFERENCES `intra_users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
