<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_edivi_type_fields` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `protocol_type_id` int(11) NOT NULL,
        `section_id` int(11) NOT NULL,
        `field_definition_id` int(11) NOT NULL,
        `enabled` tinyint(1) NOT NULL DEFAULT 1,
        `is_required` tinyint(1) NOT NULL DEFAULT 0,
        `sort_order` int(11) NOT NULL DEFAULT 0,
        `column_width` enum('full','half','third','quarter') DEFAULT 'full',
        `group_key` varchar(100) DEFAULT NULL COMMENT 'Visuelle Gruppierung z.B. atemwege, kreislauf',
        `group_label` varchar(255) DEFAULT NULL COMMENT 'Gruppen-Ueberschrift',
        `quickfill_group` varchar(100) DEFAULT NULL COMMENT 'Fuer ohne path. Befund Schnellauswahl',
        `override_options_json` text DEFAULT NULL COMMENT 'Pro-Typ Option-Override (selten)',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_type_section_field` (`protocol_type_id`, `section_id`, `field_definition_id`),
    FOREIGN KEY (`protocol_type_id`) REFERENCES `intra_edivi_protocol_types`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`section_id`) REFERENCES `intra_edivi_sections`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`field_definition_id`) REFERENCES `intra_edivi_field_definitions`(`id`) ON DELETE CASCADE,
    INDEX `idx_type_section_sort` (`protocol_type_id`, `section_id`, `sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
