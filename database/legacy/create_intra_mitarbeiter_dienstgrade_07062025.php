<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_mitarbeiter_dienstgrade` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `priority` int(11) NOT NULL,
        `name` varchar(255) NOT NULL,
        `name_m` varchar(255) NOT NULL,
        `name_w` varchar(255) NOT NULL,
        `badge` varchar(255) DEFAULT NULL,
        `archive` tinyint(1) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
