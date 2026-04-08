<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_antraege` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `uniqueid` varchar(10) NOT NULL,
        `antragstyp_id` int(11) NOT NULL,
        `name_dn` varchar(255) NOT NULL,
        `dienstgrad` varchar(100) DEFAULT NULL,
        `discordid` varchar(255) DEFAULT NULL,
        `cirs_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Bearbeitung, 1=Abgelehnt, 2=Aufgeschoben, 3=Angenommen',
        `cirs_manager` varchar(255) DEFAULT NULL,
        `cirs_text` text DEFAULT NULL COMMENT 'Bemerkung des Bearbeiters',
        `time_added` datetime NOT NULL DEFAULT current_timestamp(),
        `cirs_time` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniqueid` (`uniqueid`),
        FOREIGN KEY (`antragstyp_id`) REFERENCES `intra_antrag_typen`(`id`),
        INDEX `idx_status` (`cirs_status`),
        INDEX `idx_uniqueid` (`uniqueid`),
        INDEX `idx_typ` (`antragstyp_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
