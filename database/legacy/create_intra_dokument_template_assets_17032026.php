<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_dokument_template_assets` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `template_id` int(11) DEFAULT NULL COMMENT 'NULL = shared/global asset',
        `filename` varchar(255) NOT NULL,
        `original_name` varchar(255) NOT NULL,
        `mime_type` varchar(100) NOT NULL,
        `file_size` int(11) NOT NULL,
        `width_px` int(11) DEFAULT NULL,
        `height_px` int(11) DEFAULT NULL,
        `asset_type` enum('image','background','logo','signature') DEFAULT 'image',
        `uploaded_by` int(11) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_template_assets` (`template_id`, `asset_type`),
        CONSTRAINT `FK_template_assets_templates` FOREIGN KEY (`template_id`) REFERENCES `intra_dokument_templates` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
