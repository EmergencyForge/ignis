<?php
try {
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `intra_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `discord_id` varchar(255) NOT NULL,
  `aktenid` int(11) DEFAULT NULL,
  `role` int(11) NOT NULL DEFAULT 0,
  `full_admin` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `FK_intra_users_intra_users_roles` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
