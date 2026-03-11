<?php
try {
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `intra_enotf_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_identifier` varchar(64) NOT NULL COMMENT 'Fahrzeug-Kennung',
  `fahrername` varchar(255) DEFAULT NULL,
  `fahrerquali` varchar(32) DEFAULT NULL,
  `beifahrername` varchar(255) DEFAULT NULL,
  `beifahrerquali` varchar(32) DEFAULT NULL,
  `praktikantname` varchar(255) DEFAULT NULL,
  `praktikantquali` varchar(32) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vehicle_active` (`vehicle_identifier`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}

try {
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `intra_enotf_session_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL COMMENT 'FK zu intra_enotf_sessions.id',
  `session_token` varchar(64) NOT NULL COMMENT 'Individueller Browser-Token',
  `position` enum('fahrer','beifahrer','praktikant') NOT NULL COMMENT 'Position im Fahrzeug',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_session_token` (`session_token`),
  KEY `idx_session_id` (`session_id`),
  CONSTRAINT `fk_session_members_session` FOREIGN KEY (`session_id`) REFERENCES `intra_enotf_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
