<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_edivi_type_sections` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `protocol_type_id` int(11) NOT NULL,
        `section_id` int(11) NOT NULL,
        `enabled` tinyint(1) NOT NULL DEFAULT 1,
        `sort_order` int(11) NOT NULL DEFAULT 0,
        `is_required` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Sektion muss ausgefuellt werden',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_type_section` (`protocol_type_id`, `section_id`),
    FOREIGN KEY (`protocol_type_id`) REFERENCES `intra_edivi_protocol_types`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`section_id`) REFERENCES `intra_edivi_sections`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
