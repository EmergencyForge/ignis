<?php
// Erstellt hierarchische Kategorien und Tags für die Wissensdatenbank

try {
    // 1. Kategorien-Tabelle (hierarchisch via parent_id)
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_kb_categories` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `parent_id` INT DEFAULT NULL,
        `name` VARCHAR(100) NOT NULL,
        `slug` VARCHAR(100) NOT NULL,
        `icon` VARCHAR(50) DEFAULT NULL,
        `sort_order` INT NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_parent` (`parent_id`),
        INDEX `idx_slug` (`slug`),
        CONSTRAINT `fk_kb_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `intra_kb_categories`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    SQL;
    $pdo->exec($sql);

    // 2. Tags-Tabelle
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_kb_tags` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(50) NOT NULL UNIQUE,
        `color` VARCHAR(7) NOT NULL DEFAULT '#6c757d',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    SQL;
    $pdo->exec($sql);

    // 3. Junction-Tabelle: Einträge ↔ Tags (n:m)
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_kb_entry_tags` (
        `entry_id` INT NOT NULL,
        `tag_id` INT NOT NULL,
        PRIMARY KEY (`entry_id`, `tag_id`),
        CONSTRAINT `fk_kb_et_entry` FOREIGN KEY (`entry_id`) REFERENCES `intra_kb_entries`(`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_kb_et_tag` FOREIGN KEY (`tag_id`) REFERENCES `intra_kb_tags`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    SQL;
    $pdo->exec($sql);

    // 4. category_id Spalte zu kb_entries hinzufügen
    try {
        $pdo->exec("ALTER TABLE `intra_kb_entries` ADD COLUMN `category_id` INT DEFAULT NULL AFTER `type`");
        $pdo->exec("ALTER TABLE `intra_kb_entries` ADD INDEX `idx_category` (`category_id`)");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') === false && strpos($e->getMessage(), 'Duplicate key') === false) {
            throw $e;
        }
    }

    echo "KB Kategorien & Tags erstellt\n";
} catch (PDOException $e) {
    echo "Fehler: " . $e->getMessage() . "\n";
}
