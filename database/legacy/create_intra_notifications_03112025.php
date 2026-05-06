<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_notifications` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL COMMENT 'ID of user to notify',
        `type` varchar(50) NOT NULL COMMENT 'notification type: antrag, protokoll, dokument',
        `title` varchar(255) NOT NULL COMMENT 'Notification title',
        `message` text DEFAULT NULL COMMENT 'Notification message',
        `link` varchar(512) DEFAULT NULL COMMENT 'Link to related item',
        `is_read` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=unread, 1=read',
        `created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `read_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `FK_intra_notifications_user` (`user_id`),
        KEY `idx_user_read` (`user_id`, `is_read`),
        KEY `idx_created` (`created_at`),
        CONSTRAINT `FK_intra_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `intra_users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
