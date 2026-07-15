<?php
/**
 * Knowledge Base (Wissensdatenbank) - Main entries table
 * Supports three types: general, medication, measure
 */
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_kb_entries` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `type` ENUM('general', 'medication', 'measure') NOT NULL DEFAULT 'general',
        `title` VARCHAR(255) NOT NULL,
        `subtitle` VARCHAR(255) NULL,
        
        -- Color coding for competency levels
        `competency_level` ENUM('basis', 'rettsan', 'notsan_2c', 'notsan_2a', 'notarzt') NULL,
        
        -- General content (for all types, can use HTML from CKEditor)
        `content` LONGTEXT NULL,
        
        -- Medication-specific fields
        `med_wirkstoff` VARCHAR(255) NULL,
        `med_wirkstoffgruppe` VARCHAR(255) NULL,
        `med_wirkmechanismus` TEXT NULL,
        `med_indikationen` TEXT NULL,
        `med_kontraindikationen` TEXT NULL,
        `med_uaw` TEXT NULL,
        `med_dosierung` TEXT NULL,
        `med_besonderheiten` TEXT NULL,
        
        -- Measure-specific fields
        `mass_wirkprinzip` TEXT NULL,
        `mass_indikationen` TEXT NULL,
        `mass_kontraindikationen` TEXT NULL,
        `mass_risiken` TEXT NULL,
        `mass_alternativen` TEXT NULL,
        `mass_durchfuehrung` TEXT NULL,
        
        -- Metadata
        `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
        `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `created_by` INT NULL,
        `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        `updated_by` INT NULL,
        
        INDEX idx_type (type),
        INDEX idx_pinned (is_pinned),
        INDEX idx_archived (is_archived),
        INDEX idx_competency (competency_level),
        INDEX idx_created (created_at),
        INDEX idx_title (title),
        
        FOREIGN KEY (created_by) REFERENCES intra_users(id) ON DELETE SET NULL,
        FOREIGN KEY (updated_by) REFERENCES intra_users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
