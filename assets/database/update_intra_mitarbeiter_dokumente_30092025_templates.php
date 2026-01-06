<?php
try {
    // Template-Tabellen erstellen
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_dokument_templates` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `category` ENUM('urkunde', 'zertifikat', 'schreiben', 'sonstiges'),
        `description` TEXT,
        `template_file` VARCHAR(255),
        `config` JSON DEFAULT NULL,
        `is_system` BOOLEAN DEFAULT 0,
        `created_by` INT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    SQL;
    $pdo->exec($sql);

    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_dokument_template_fields` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `template_id` INT NOT NULL,
        `field_name` VARCHAR(100) NOT NULL,
        `field_label` VARCHAR(255) NOT NULL,
        `field_type` ENUM('text', 'textarea', 'date', 'select', 'number', 'richtext', 'dbdg', 'dbrd', 'db_dg', 'db_rdq'),
        `field_options` TEXT,
        `is_required` BOOLEAN DEFAULT 0,
        `gender_specific` TINYINT(1) DEFAULT 0,
        `sort_order` INT DEFAULT 0,
        `validation_rules` TEXT,
        FOREIGN KEY (`template_id`) REFERENCES `intra_dokument_templates`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    SQL;
    $pdo->exec($sql);

    // Dokumente-Tabelle erweitern - SEPARATE Statements für MySQL 8.0
    $sql = "ALTER TABLE `intra_mitarbeiter_dokumente` 
            ADD COLUMN IF NOT EXISTS `template_id` INT DEFAULT NULL";
    $pdo->exec($sql);

    $sql = "ALTER TABLE `intra_mitarbeiter_dokumente` 
            ADD COLUMN IF NOT EXISTS `custom_data` TEXT DEFAULT NULL";
    $pdo->exec($sql);

    echo "✓ Template-Tabellen erstellt\n";
} catch (PDOException $e) {
    echo "Fehler: " . $e->getMessage() . "\n";
}
