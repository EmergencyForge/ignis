<?php
try {
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `intra_manv_ressourcen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `manv_lage_id` int(11) NOT NULL,
  `typ` enum('fahrzeug','personal','material') DEFAULT 'fahrzeug',
  `bezeichnung` varchar(255) NOT NULL COMMENT 'z.B. RTW, NAW, LNA, Behandlungsplatz',
  `rufname` varchar(100) DEFAULT NULL COMMENT 'z.B. Florian ABC 83-1',
  `fahrzeugtyp` varchar(50) DEFAULT NULL COMMENT 'RTW, NAW, RTH, KTW, etc.',
  `lokalisation` varchar(255) DEFAULT NULL COMMENT 'Position an der Einsatzstelle',
  `status` enum('verfuegbar','im_einsatz','nicht_verfuegbar') DEFAULT 'verfuegbar',
  `besatzung` text DEFAULT NULL,
  `notizen` text DEFAULT NULL,
  `erstellt_am` datetime DEFAULT current_timestamp(),
  `geaendert_am` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_manv_lage` (`manv_lage_id`),
  KEY `idx_typ` (`typ`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_manv_ressource_lage` FOREIGN KEY (`manv_lage_id`) REFERENCES `intra_manv_lagen` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='MANV-Ressourcen (Fahrzeuge, Personal, Material)';
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    if (strpos($message, "already exists") === false && strpos($message, "Duplicate column") === false) {
        echo "❌ Fehler beim Erstellen der Tabelle intra_manv_ressourcen: " . $message;
    }
}
