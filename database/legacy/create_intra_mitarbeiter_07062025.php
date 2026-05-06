<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_mitarbeiter` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `fullname` varchar(255) NOT NULL,
        `gebdatum` date NOT NULL,
        `charakterid` varchar(255) NOT NULL,
        `geschlecht` tinyint(1) NOT NULL,
        `forumprofil` int(5) DEFAULT NULL,
        `discordtag` varchar(255) DEFAULT NULL,
        `telefonnr` varchar(255) DEFAULT NULL,
        `dienstnr` varchar(255) NOT NULL,
        `einstdatum` date NOT NULL,
        `dienstgrad` int(11) NOT NULL DEFAULT 0,
        `qualifw2` int(11) NOT NULL DEFAULT 0,
        `qualird` int(11) NOT NULL DEFAULT 0,
        `zusatz` varchar(255) DEFAULT NULL,
        `fachdienste` longtext DEFAULT NULL,
        `createdate` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `dienstnr` (`dienstnr`),
        KEY `FK_intra_mitarbeiter_intra_mitarbeiter_dienstgrade` (`dienstgrad`),
        KEY `FK_intra_mitarbeiter_intra_mitarbeiter_fwquali` (`qualifw2`),
        KEY `FK_intra_mitarbeiter_intra_mitarbeiter_rdquali` (`qualird`),
        CONSTRAINT `FK_intra_mitarbeiter_intra_mitarbeiter_dienstgrade` FOREIGN KEY (`dienstgrad`) REFERENCES `intra_mitarbeiter_dienstgrade` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE,
        CONSTRAINT `FK_intra_mitarbeiter_intra_mitarbeiter_fwquali` FOREIGN KEY (`qualifw2`) REFERENCES `intra_mitarbeiter_fwquali` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE,
        CONSTRAINT `FK_intra_mitarbeiter_intra_mitarbeiter_rdquali` FOREIGN KEY (`qualird`) REFERENCES `intra_mitarbeiter_rdquali` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE
    ) ENGINE=InnoDB AUTO_INCREMENT=1038 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
