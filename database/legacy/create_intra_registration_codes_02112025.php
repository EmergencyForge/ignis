<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_registration_codes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `code` varchar(255) NOT NULL,
        `created_by` int(11) DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `used_by` int(11) DEFAULT NULL,
        `used_at` timestamp NULL DEFAULT NULL,
        `is_used` tinyint(1) DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `code` (`code`),
        KEY `created_by` (`created_by`),
        KEY `used_by` (`used_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
