<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_enotf_quicklinks` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `title` varchar(255) NOT NULL,
        `url` varchar(500) NOT NULL,
        `icon` varchar(100) NOT NULL DEFAULT 'fa-solid fa-link',
        `category` enum('schnellzugriff','verwaltung') NOT NULL DEFAULT 'schnellzugriff',
        `sort_order` int(11) NOT NULL DEFAULT 0,
        `col_width` varchar(20) NOT NULL DEFAULT 'col-6',
        `active` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_category_active` (`category`, `active`),
        KEY `idx_sort_order` (`sort_order`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
