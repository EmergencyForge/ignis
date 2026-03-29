<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_edivi_protocol_types` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `slug` varchar(50) NOT NULL,
        `name` varchar(255) NOT NULL,
        `short_name` varchar(10) NOT NULL,
        `description` text DEFAULT NULL,
        `color` varchar(7) DEFAULT '#dc3545',
        `icon` varchar(50) DEFAULT NULL,
        `is_builtin` tinyint(1) NOT NULL DEFAULT 0,
        `active` tinyint(1) NOT NULL DEFAULT 1,
        `sort_order` int(11) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `created_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`),
    INDEX `idx_active_sort` (`active`, `sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
