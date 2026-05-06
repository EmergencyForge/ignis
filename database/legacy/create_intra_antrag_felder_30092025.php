<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_antrag_felder` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `antragstyp_id` int(11) NOT NULL,
        `feldname` varchar(100) NOT NULL COMMENT 'Technischer Name des Feldes',
        `label` varchar(255) NOT NULL COMMENT 'Anzeigetext für das Feld',
        `feldtyp` enum('text','textarea','number','date','select','checkbox','email','time','tel') NOT NULL,
        `optionen` text DEFAULT NULL COMMENT 'JSON für Select-Optionen',
        `pflichtfeld` tinyint(1) NOT NULL DEFAULT 0,
        `platzhalter` varchar(255) DEFAULT NULL,
        `sortierung` int(11) DEFAULT 0,
        `breite` enum('full','half') DEFAULT 'full',
        `standardwert` varchar(255) DEFAULT NULL,
        `hinweistext` text DEFAULT NULL,
        `readonly` tinyint(1) DEFAULT 0 COMMENT 'Feld ist nur lesbar',
        `auto_fill` varchar(50) DEFAULT NULL COMMENT 'Automatisch ausfüllen mit: fullname, dienstnr, dienstgrad, discordtag',
    PRIMARY KEY (`id`),
    FOREIGN KEY (`antragstyp_id`) REFERENCES `intra_antrag_typen`(`id`) ON DELETE CASCADE,
    INDEX `idx_antragstyp` (`antragstyp_id`),
    INDEX `idx_sortierung` (`sortierung`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
