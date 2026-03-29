<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_edivi_validation_rules` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `protocol_type_id` int(11) NOT NULL,
        `name` varchar(255) NOT NULL COMMENT 'Menschenlesbarer Regelname',
        `rule_json` text NOT NULL COMMENT 'Condition-Tree als JSON',
        `error_message` varchar(500) NOT NULL,
        `severity` enum('error','warning') NOT NULL DEFAULT 'error',
        `active` tinyint(1) NOT NULL DEFAULT 1,
        `sort_order` int(11) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    FOREIGN KEY (`protocol_type_id`) REFERENCES `intra_edivi_protocol_types`(`id`) ON DELETE CASCADE,
    INDEX `idx_type_active` (`protocol_type_id`, `active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
