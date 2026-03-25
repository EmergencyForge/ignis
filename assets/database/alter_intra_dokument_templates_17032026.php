<?php
try {
    // Add editor_type column
    $sql = "ALTER TABLE `intra_dokument_templates` ADD COLUMN `editor_type` enum('twig','visual') NOT NULL DEFAULT 'twig' AFTER `template_file`";
    $pdo->exec($sql);
} catch (PDOException $e) {
    // Column may already exist
    if (strpos($e->getMessage(), 'Duplicate column') === false) {
        echo $e->getMessage();
    }
}

try {
    // Add layout_id column
    $sql = "ALTER TABLE `intra_dokument_templates` ADD COLUMN `layout_id` int(11) DEFAULT NULL AFTER `editor_type`";
    $pdo->exec($sql);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') === false) {
        echo $e->getMessage();
    }
}
