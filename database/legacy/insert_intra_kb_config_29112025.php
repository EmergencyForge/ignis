<?php
/**
 * Insert default configuration for Knowledge Base visibility
 */
try {
    $sql = <<<SQL
    INSERT INTO `intra_config` 
        (`config_key`, `config_value`, `config_type`, `category`, `description`, `is_editable`, `display_order`)
    VALUES 
        ('KB_PUBLIC_ACCESS', 'false', 'boolean', 'funktionen', 'Soll die Wissensdatenbank ohne Login einsehbar sein?', 1, 60)
    ON DUPLICATE KEY UPDATE
        `config_key` = `config_key`;
    SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
