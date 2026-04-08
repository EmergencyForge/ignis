<?php

/**
 * Add configuration category "Rechtliches" with Impressum and Datenschutz URL options
 */
try {
    $configs = [
        [
            'key' => 'LEGAL_IMPRESSUM_URL',
            'value' => '',
            'type' => 'url',
            'category' => 'rechtliches',
            'description' => 'URL zum Impressum (leer lassen um Link auszublenden)',
            'editable' => 1,
            'order' => 50
        ],
        [
            'key' => 'LEGAL_DATENSCHUTZ_URL',
            'value' => '',
            'type' => 'url',
            'category' => 'rechtliches',
            'description' => 'URL zur Datenschutzerklärung (leer lassen um Link auszublenden)',
            'editable' => 1,
            'order' => 51
        ],
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
            'key' => $config['key'],
            'value' => $config['value'],
            'type' => $config['type'],
            'category' => $config['category'],
            'description' => $config['description'],
            'editable' => $config['editable'],
            'order' => $config['order']
        ]);
    }
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
