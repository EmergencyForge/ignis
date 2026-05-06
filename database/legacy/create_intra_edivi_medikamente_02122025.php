<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_edivi_medikamente` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `priority` int(11) NOT NULL DEFAULT 0,
        `wirkstoff` varchar(255) NOT NULL,
        `herstellername` varchar(255) DEFAULT NULL,
        `dosierungen` TEXT DEFAULT NULL,
        `active` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_wirkstoff` (`wirkstoff`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
