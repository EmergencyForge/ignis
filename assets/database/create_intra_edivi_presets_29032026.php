<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_edivi_presets` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `description` text DEFAULT NULL,
        `is_builtin` tinyint(1) NOT NULL DEFAULT 0,
        `preset_json` longtext NOT NULL COMMENT 'Kompletter Konfigurations-Snapshot als JSON',
        `version` varchar(20) NOT NULL DEFAULT '1.0',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `created_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
