<?php

/**
 * Insert config entry for modular eNOTF forms feature flag
 * Date: 2026-03-29
 */

if (!isset($pdo)) {
    die('Database connection not available');
}

try {
    $configs = [
        ['key' => 'ENOTF_MODULAR_FORMS', 'value' => 'false', 'type' => 'boolean', 'category' => 'funktionen', 'description' => 'Modulares Protokollsystem aktivieren (dynamische Sektionen, Felder, Protokolltypen)', 'editable' => 1, 'order' => 40],
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
