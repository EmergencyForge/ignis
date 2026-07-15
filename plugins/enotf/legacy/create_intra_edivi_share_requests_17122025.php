<?php
try {
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `intra_edivi_share_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_enr` varchar(255) NOT NULL COMMENT 'ENR des Quell-Protokolls',
  `source_protocol_id` int(11) NOT NULL COMMENT 'ID des Quell-Protokolls',
  `source_vehicle` varchar(255) NOT NULL COMMENT 'Fahrzeug das teilt',
  `target_vehicle` varchar(255) NOT NULL COMMENT 'Fahrzeug das empfängt',
  `status` enum('pending','accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `response_at` timestamp NULL DEFAULT NULL,
  `response_by` varchar(255) DEFAULT NULL COMMENT 'Name der Person die geantwortet hat',
  `action_taken` enum('merged','new_protocol') DEFAULT NULL COMMENT 'Aktion die durchgeführt wurde',
  `new_enr` varchar(255) DEFAULT NULL COMMENT 'Neue ENR falls neues Protokoll erstellt',
  PRIMARY KEY (`id`),
  KEY `idx_target_vehicle` (`target_vehicle`,`status`),
  KEY `idx_source_protocol` (`source_protocol_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
