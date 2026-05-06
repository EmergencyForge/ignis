<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_uploads` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `file_name` varchar(255) NOT NULL,
        `file_type` varchar(255) NOT NULL,
        `file_size` varchar(255) NOT NULL,
        `user_name` varchar(255) NOT NULL,
        `upload_time` datetime NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
