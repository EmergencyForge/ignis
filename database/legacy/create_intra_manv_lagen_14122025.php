<?php
try {
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `intra_manv_lagen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `einsatznummer` varchar(50) NOT NULL,
  `einsatzort` varchar(255) NOT NULL,
  `einsatzanlass` text DEFAULT NULL,
  `lna_name` varchar(255) DEFAULT NULL COMMENT 'Leitender Notarzt',
  `lna_mitarbeiter_id` int(11) DEFAULT NULL,
  `orgl_name` varchar(255) DEFAULT NULL COMMENT 'Organisatorischer Leiter',
  `orgl_mitarbeiter_id` int(11) DEFAULT NULL,
  `status` enum('aktiv','abgeschlossen','archiviert') DEFAULT 'aktiv',
  `einsatzbeginn` datetime DEFAULT NULL,
  `einsatzende` datetime DEFAULT NULL,
  `erstellt_am` datetime DEFAULT current_timestamp(),
  `erstellt_von` int(11) DEFAULT NULL,
  `geaendert_am` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `geaendert_von` int(11) DEFAULT NULL,
  `notizen` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_einsatznummer` (`einsatznummer`),
  KEY `idx_status` (`status`),
  KEY `fk_lna_mitarbeiter` (`lna_mitarbeiter_id`),
  KEY `fk_orgl_mitarbeiter` (`orgl_mitarbeiter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='MANV-Lagen (Massenanfall von Verletzten)';
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    if (strpos($message, "already exists") === false && strpos($message, "Duplicate column") === false) {
        echo "❌ Fehler beim Erstellen der Tabelle intra_manv_lagen: " . $message;
    }
}
