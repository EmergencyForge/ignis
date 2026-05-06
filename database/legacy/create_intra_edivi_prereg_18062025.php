<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_edivi_prereg` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `priority` tinyint(1) NOT NULL DEFAULT 0,
        `arrival` datetime NOT NULL DEFAULT current_timestamp(),
        `fahrzeug` varchar(255) DEFAULT NULL,
        `diagnose` varchar(255) DEFAULT NULL,
        `geschlecht` tinyint(1) NOT NULL DEFAULT 0,
        `alter` varchar(255) DEFAULT NULL,
        `text` text DEFAULT NULL,
        `kreislauf` tinyint(1) DEFAULT 1,
        `gcs` varchar(255) DEFAULT '15',
        `intubiert` tinyint(1) DEFAULT 0,
        `ziel` varchar(255) NOT NULL,
        `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
        `active` tinyint(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
