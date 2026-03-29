<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_edivi_sections` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `slug` varchar(50) NOT NULL,
        `name` varchar(255) NOT NULL,
        `icon` varchar(50) DEFAULT NULL,
        `is_builtin` tinyint(1) NOT NULL DEFAULT 0,
        `has_subsections` tinyint(1) NOT NULL DEFAULT 0,
        `component_template` varchar(100) DEFAULT NULL COMMENT 'NULL=generisch, sonst Spezial-Widget (verlauf, vitals_chart)',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
