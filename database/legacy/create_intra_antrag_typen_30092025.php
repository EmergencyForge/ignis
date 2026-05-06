<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_antrag_typen` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `beschreibung` text DEFAULT NULL,
        `icon` varchar(50) DEFAULT 'fa-solid fa-file-alt',
        `aktiv` tinyint(1) NOT NULL DEFAULT 1,
        `sortierung` int(11) DEFAULT 0,
        `tabelle_name` varchar(100) DEFAULT NULL COMMENT 'Name der Zieltabelle (z.B. intra_antrag_bef)',
        `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
        `erstellt_von` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_aktiv` (`aktiv`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
