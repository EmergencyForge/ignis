<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_fahrzeuge_beladung_tiles` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `category` int(11) NOT NULL DEFAULT 0,
        `amount` int(11) NOT NULL DEFAULT 0,
        `title` varchar(255) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `FK_beladung_categories` (`category`),
        CONSTRAINT `FK_beladung_categories` FOREIGN KEY (`category`) REFERENCES `intra_fahrzeuge_beladung_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
