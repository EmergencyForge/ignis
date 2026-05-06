<?php
try {
    // Add blood sugar unit configuration option
    $config = [
        'key' => 'ENOTF_BZ_UNIT',
        'value' => 'mg/dl',
        'type' => 'string',
        'category' => 'funktionen',
        'description' => 'Einheit für Blutzuckerwerte (mg/dl oder mmol/l)',
        'editable' => 1,
        'order' => 37
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

    $stmt->execute([
        'key' => $config['key'],
        'value' => $config['value'],
        'type' => $config['type'],
        'category' => $config['category'],
        'description' => $config['description'],
        'editable' => $config['editable'],
        'order' => $config['order']
    ]);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
