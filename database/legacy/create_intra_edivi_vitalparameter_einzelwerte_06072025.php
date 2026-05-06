<?php
try {
    // Create table
    $sql = <<<SQL
    CREATE TABLE `intra_edivi_vitalparameter_einzelwerte` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `enr` VARCHAR(50) NOT NULL,
        `zeitpunkt` DATETIME NOT NULL,
        `parameter_name` VARCHAR(100) NOT NULL,
        `parameter_wert` VARCHAR(50) NOT NULL,
        `parameter_einheit` VARCHAR(20) NOT NULL,
        `erstellt_von` VARCHAR(100) NOT NULL,
        `erstellt_am` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `geloescht` TINYINT(1) DEFAULT 0,
        `geloescht_am` TIMESTAMP NULL,
        `geloescht_von` VARCHAR(100) NULL,
        PRIMARY KEY (`id`),
        INDEX `idx_enr` (`enr`),
        INDEX `idx_zeitpunkt` (`zeitpunkt`),
        INDEX `idx_parameter_name` (`parameter_name`),
        INDEX `idx_geloescht` (`geloescht`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    $pdo->exec($sql);
    
    // Create trigger (DELIMITER is a MySQL client command, not SQL - must be executed separately)
    $triggerSql = <<<SQL
    CREATE TRIGGER `before_delete_vitalparameter_einzelwerte` 
    BEFORE DELETE ON `intra_edivi_vitalparameter_einzelwerte`
    FOR EACH ROW
    BEGIN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Direct deletion not allowed. Use soft delete instead.';
    END
SQL;

    $pdo->exec($triggerSql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
