<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_mitarbeiter_rdquali` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `priority` int(11) NOT NULL,
        `name` varchar(255) NOT NULL,
        `name_m` varchar(255) NOT NULL,
        `name_w` varchar(255) NOT NULL,
        `abkuerzung` varchar(50) DEFAULT NULL,
        `none` tinyint(1) NOT NULL DEFAULT 0,
        `trainable` tinyint(1) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
