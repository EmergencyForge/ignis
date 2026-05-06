<?php
try {
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `intra_users_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `priority` int(11) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL,
  `color` varchar(255) DEFAULT NULL,
  `permissions` longtext DEFAULT NULL,
  `default` tinyint(1) NOT NULL DEFAULT 0,
  `admin` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
