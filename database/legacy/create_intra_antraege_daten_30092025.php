<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_antraege_daten` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `antrag_id` int(11) NOT NULL,
        `feldname` varchar(100) NOT NULL,
        `wert` text DEFAULT NULL,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`antrag_id`) REFERENCES `intra_antraege`(`id`) ON DELETE CASCADE,
        INDEX `idx_antrag` (`antrag_id`),
        INDEX `idx_feldname` (`feldname`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
