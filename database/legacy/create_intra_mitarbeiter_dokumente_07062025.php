<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_mitarbeiter_dokumente` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `docid` int(11) NOT NULL,
        `type` tinyint(2) NOT NULL DEFAULT 0,
        `anrede` tinyint(1) NOT NULL DEFAULT 0,
        `erhalter` varchar(255) DEFAULT NULL,
        `inhalt` longtext DEFAULT NULL,
        `suspendtime` date DEFAULT NULL,
        `erhalter_gebdat` date DEFAULT NULL,
        `erhalter_rang` tinyint(2) DEFAULT NULL,
        `erhalter_rang_rd` tinyint(2) DEFAULT NULL,
        `erhalter_quali` tinyint(2) DEFAULT NULL,
        `ausstellungsdatum` date DEFAULT NULL,
        `ausstellerid` int(11) NOT NULL,
        `aussteller_name` varchar(255) DEFAULT NULL,
        `aussteller_rang` tinyint(2) DEFAULT NULL,
        `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
        `profileid` int(11) DEFAULT NULL,
        `discordid` varchar(255) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `docid` (`docid`),
        KEY `FK_intra_mitarbeiter_dokumente_intra_mitarbeiter` (`profileid`),
        CONSTRAINT `FK_intra_mitarbeiter_dokumente_intra_mitarbeiter` FOREIGN KEY (`profileid`) REFERENCES `intra_mitarbeiter` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB AUTO_INCREMENT=2291 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
