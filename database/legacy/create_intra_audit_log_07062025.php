<?php
try {
  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `intra_audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user` int(11) NOT NULL DEFAULT 0,
  `module` varchar(255) DEFAULT NULL,
  `action` text DEFAULT NULL,
  `details` text DEFAULT NULL,
  `global` tinyint(1) NOT NULL DEFAULT 0,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `FK_intra_audit_log_intra_users` (`user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

  $pdo->exec($sql);
} catch (PDOException $e) {
  $message = $e->getMessage();
  echo $message;
}
