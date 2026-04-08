<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_fahrzeuge_beladung_categories` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `title` varchar(255) NOT NULL,
        `type` tinyint(1) NOT NULL DEFAULT 0,
        `priority` int(11) NOT NULL DEFAULT 0,
        `veh_type` varchar(255) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
