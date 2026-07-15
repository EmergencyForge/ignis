<?php

/**
 * Add configuration option for fire incident vehicle login authentication requirement
 */
try {
    $config = [
        'key' => 'FIRE_INCIDENT_REQUIRE_USER_AUTH',
        'value' => 'false',
        'type' => 'boolean',
        'category' => 'funktionen',
        'description' => 'Wird eine Registrierung/Anmeldung im Hauptsystem für die Fahrzeuganmeldung im Einsatzprotokoll vorausgesetzt?',
        'editable' => 1,
        'order' => 35
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
