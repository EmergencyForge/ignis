<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_edivi_custom_values` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `protocol_id` int(11) NOT NULL COMMENT 'FK zu intra_edivi.id',
        `enr` varchar(255) NOT NULL COMMENT 'Redundant fuer schnelle Lookups',
        `field_key` varchar(100) NOT NULL,
        `field_value` text DEFAULT NULL,
        `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_protocol_field` (`protocol_id`, `field_key`),
    INDEX `idx_enr` (`enr`),
    INDEX `idx_field_key` (`field_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
