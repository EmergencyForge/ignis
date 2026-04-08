<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_dokument_template_layouts` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `template_id` int(11) NOT NULL,
        `version` int(11) NOT NULL DEFAULT 1,
        `canvas_json` longtext NOT NULL COMMENT 'Fabric.js JSON export of the full canvas',
        `page_width_mm` decimal(6,2) NOT NULL DEFAULT 210.00,
        `page_height_mm` decimal(6,2) NOT NULL DEFAULT 297.00,
        `background_image_id` int(11) DEFAULT NULL,
        `is_active` tinyint(1) DEFAULT 1,
        `created_by` int(11) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_template_active` (`template_id`, `is_active`),
        CONSTRAINT `FK_template_layouts_templates` FOREIGN KEY (`template_id`) REFERENCES `intra_dokument_templates` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
