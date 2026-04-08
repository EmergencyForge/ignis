<?php
try {
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `intra_edivi_klinikcodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `enr` varchar(255) NOT NULL,
  `code` varchar(6) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `enr` (`enr`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

    $pdo->exec($sql);
    echo "Tabelle intra_edivi_klinikcodes wurde erfolgreich erstellt.\n";
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo "Fehler beim Erstellen der Tabelle: " . $message . "\n";
}
