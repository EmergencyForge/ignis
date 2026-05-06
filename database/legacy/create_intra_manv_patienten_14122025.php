<?php
try {
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `intra_manv_patienten` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `manv_lage_id` int(11) NOT NULL,
  `patienten_nummer` varchar(50) NOT NULL COMMENT 'z.B. MANV-001',
  `name` varchar(255) DEFAULT NULL,
  `vorname` varchar(255) DEFAULT NULL,
  `geburtsdatum` date DEFAULT NULL,
  `geschlecht` enum('m','w','d','unbekannt') DEFAULT 'unbekannt',
  `sichtungskategorie` enum('SK1','SK2','SK3','SK4','tot') DEFAULT NULL COMMENT 'SK1=rot/sofort, SK2=gelb/dringend, SK3=grün/später, SK4=blau/abwartend',
  `sichtungskategorie_zeit` datetime DEFAULT NULL,
  `sichtungskategorie_geaendert_von` int(11) DEFAULT NULL,
  `transportmittel` varchar(100) DEFAULT NULL COMMENT 'RTW, NAW, RTH, etc.',
  `transportmittel_rufname` varchar(100) DEFAULT NULL COMMENT 'z.B. Florian ABC 83-1',
  `fahrzeug_lokalisation` varchar(255) DEFAULT NULL COMMENT 'Position an der Einsatzstelle',
  `transportziel` varchar(255) DEFAULT NULL,
  `transport_abfahrt` datetime DEFAULT NULL,
  `transport_ankunft` datetime DEFAULT NULL,
  `verletzungen` text DEFAULT NULL,
  `massnahmen` text DEFAULT NULL,
  `notizen` text DEFAULT NULL,
  `erstellt_am` datetime DEFAULT current_timestamp(),
  `erstellt_von` int(11) DEFAULT NULL,
  `geaendert_am` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `geaendert_von` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_manv_lage` (`manv_lage_id`),
  KEY `idx_patienten_nummer` (`patienten_nummer`),
  KEY `idx_sichtungskategorie` (`sichtungskategorie`),
  CONSTRAINT `fk_manv_patient_lage` FOREIGN KEY (`manv_lage_id`) REFERENCES `intra_manv_lagen` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='MANV-Patienten';
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    if (strpos($message, "already exists") === false && strpos($message, "Duplicate column") === false) {
        echo "❌ Fehler beim Erstellen der Tabelle intra_manv_patienten: " . $message;
    }
}
