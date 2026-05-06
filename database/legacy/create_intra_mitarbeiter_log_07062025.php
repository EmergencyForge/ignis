<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_mitarbeiter_log` (
        `logid` int(11) NOT NULL AUTO_INCREMENT,
        `profilid` int(11) NOT NULL,
        `type` tinyint(1) NOT NULL DEFAULT 0,
        `content` longtext NOT NULL,
        `datetime` datetime NOT NULL DEFAULT current_timestamp(),
        `paneluser` varchar(255) NOT NULL,
        PRIMARY KEY (`logid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=6412 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
