<?php
try {
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `intra_manv_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `manv_lage_id` int(11) NOT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `aktion` varchar(100) NOT NULL COMMENT 'z.B. patient_erstellt, sichtung_geaendert, transport_zugewiesen',
  `beschreibung` text DEFAULT NULL,
  `benutzer_id` int(11) DEFAULT NULL,
  `benutzer_name` varchar(255) DEFAULT NULL,
  `referenz_typ` varchar(50) DEFAULT NULL COMMENT 'patient, ressource, etc.',
  `referenz_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_manv_lage` (`manv_lage_id`),
  KEY `idx_timestamp` (`timestamp`),
  CONSTRAINT `fk_manv_log_lage` FOREIGN KEY (`manv_lage_id`) REFERENCES `intra_manv_lagen` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='MANV-Aktionslog';
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    if (strpos($message, "already exists") === false && strpos($message, "Duplicate column") === false) {
        echo "❌ Fehler beim Erstellen der Tabelle intra_manv_log: " . $message;
    }
}
