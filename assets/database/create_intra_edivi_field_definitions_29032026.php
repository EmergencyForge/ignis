<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_edivi_field_definitions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `field_key` varchar(100) NOT NULL,
        `label` varchar(255) NOT NULL,
        `field_type` enum(
            'radio','checkbox','checkbox_group','text','textarea',
            'number','date','time','datetime','select','custom_dropdown',
            'json_multi_select','composite','hidden'
        ) NOT NULL,
        `options_json` text DEFAULT NULL COMMENT 'Optionen fuer Radio/Select/Checkbox als JSON',
        `widget` varchar(100) DEFAULT NULL COMMENT 'NULL=generisch, sonst Spezial-Widget-Name',
        `is_legacy_column` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Mappt auf bestehende intra_edivi-Spalte',
        `legacy_column_name` varchar(100) DEFAULT NULL COMMENT 'DB-Spaltenname wenn is_legacy_column=1',
        `is_core` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Kernfeld, nicht deaktivierbar',
        `default_value` varchar(255) DEFAULT NULL,
        `placeholder` varchar(255) DEFAULT NULL,
        `hint_text` text DEFAULT NULL,
        `input_suffix` varchar(20) DEFAULT NULL COMMENT 'Einheit z.B. mmHg, %, mg/dl',
        `min_value` varchar(50) DEFAULT NULL,
        `max_value` varchar(50) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_field_key` (`field_key`),
    INDEX `idx_legacy` (`is_legacy_column`),
    INDEX `idx_core` (`is_core`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
