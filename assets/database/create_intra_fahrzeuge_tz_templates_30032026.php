<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_fahrzeuge_tz_templates` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `grundzeichen` VARCHAR(100) NULL,
        `organisation` VARCHAR(100) NULL,
        `fachaufgabe` VARCHAR(100) NULL,
        `einheit` VARCHAR(100) NULL,
        `symbol` VARCHAR(100) NULL,
        `typ` VARCHAR(100) NULL,
        `text` VARCHAR(100) NULL,
        `created_by` INT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,

        UNIQUE KEY uniq_template_name (name),
        FOREIGN KEY (created_by) REFERENCES intra_users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    echo $e->getMessage();
}
