<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_edivi_qmlog` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `protokoll_id` int(11) NOT NULL,
        `kommentar` longtext NOT NULL,
        `log_aktion` tinyint(1) DEFAULT NULL,
        `bearbeiter` varchar(255) DEFAULT NULL,
        `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `FK_intra_edivi_qmlog_intra_edivi` (`protokoll_id`),
        CONSTRAINT `FK_intra_edivi_qmlog_intra_edivi` FOREIGN KEY (`protokoll_id`) REFERENCES `intra_edivi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB AUTO_INCREMENT=938 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
