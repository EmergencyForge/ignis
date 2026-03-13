<?php

/**
 * Insert config entries for character-based access control
 * Date: 2026-03-13
 */

if (!isset($pdo)) {
    die('Database connection not available');
}

try {
    $configs = [
        ['key' => 'ENOTF_CHAR_LOCK', 'value' => 'false', 'type' => 'boolean', 'category' => 'funktionen', 'description' => 'Charakter-Name beim eNOTF/Einsatz-Login sperren (erfordert identify-API)', 'editable' => 1, 'order' => 38],
        ['key' => 'ENOTF_JOB_FILTER', 'value' => 'false', 'type' => 'boolean', 'category' => 'funktionen', 'description' => 'Fahrzeuge nach Job filtern (erfordert identify-API)', 'editable' => 1, 'order' => 39],
    ];

    $stmt = $pdo->prepare("
        INSERT INTO intra_config (config_key, config_value, config_type, category, description, is_editable, display_order)
        VALUES (:key, :value, :type, :category, :description, :editable, :order)
        ON DUPLICATE KEY UPDATE
            config_type = VALUES(config_type),
            category = VALUES(category),
            description = VALUES(description),
            is_editable = VALUES(is_editable),
            display_order = VALUES(display_order)
    ");

    foreach ($configs as $config) {
        $stmt->execute([
            ':key' => $config['key'],
            ':value' => $config['value'],
            ':type' => $config['type'],
            ':category' => $config['category'],
            ':description' => $config['description'],
            ':editable' => $config['editable'],
            ':order' => $config['order'],
        ]);
        echo "✓ Config '{$config['key']}' eingefügt/aktualisiert\n";
    }
} catch (PDOException $e) {
    echo "✗ Fehler beim Einfügen der Config-Einträge: " . $e->getMessage() . "\n";
    throw $e;
}
