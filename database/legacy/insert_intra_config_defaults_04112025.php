<?php
try {
    // Generate a random API key for new installations (32 bytes = 64 hexadecimal characters)
    $apiKey = bin2hex(random_bytes(32));

    // Insert default configuration values from config.php
    $configs = [
        // BASIS DATEN
        ['key' => 'API_KEY', 'value' => $apiKey, 'type' => 'string', 'category' => 'basis', 'description' => 'API-Schlüssel für externe Schnittstellen', 'editable' => 0, 'order' => 1],
        ['key' => 'SYSTEM_NAME', 'value' => 'intraRP', 'type' => 'string', 'category' => 'basis', 'description' => 'Eigenname des Intranets', 'editable' => 1, 'order' => 2],
        ['key' => 'SYSTEM_COLOR', 'value' => '#d10000', 'type' => 'color', 'category' => 'basis', 'description' => 'Hauptfarbe des Systems', 'editable' => 1, 'order' => 3],
        ['key' => 'SYSTEM_URL', 'value' => 'CHANGE_ME', 'type' => 'url', 'category' => 'basis', 'description' => 'Domain des Systems (ohne https://)', 'editable' => 1, 'order' => 4],
        ['key' => 'SYSTEM_LOGO', 'value' => '/assets/img/defaultLogo.webp', 'type' => 'url', 'category' => 'basis', 'description' => 'Ort des Logos (relativer Pfad oder Link)', 'editable' => 1, 'order' => 5],
        ['key' => 'META_IMAGE_URL', 'value' => '', 'type' => 'url', 'category' => 'basis', 'description' => 'Bild für Link-Vorschau (als Link angeben)', 'editable' => 1, 'order' => 6],

        // SERVER DATEN
        ['key' => 'SERVER_NAME', 'value' => 'CHANGE_ME', 'type' => 'string', 'category' => 'server', 'description' => 'Name des Servers', 'editable' => 1, 'order' => 10],
        ['key' => 'SERVER_CITY', 'value' => 'Musterstadt', 'type' => 'string', 'category' => 'server', 'description' => 'Name der Stadt in welcher der Server spielt', 'editable' => 1, 'order' => 11],

        // RP DATEN
        ['key' => 'RP_ORGTYPE', 'value' => 'Berufsfeuerwehr', 'type' => 'string', 'category' => 'rp', 'description' => 'Art/Name der Organisation', 'editable' => 1, 'order' => 20],
        ['key' => 'RP_STREET', 'value' => 'Musterweg 0815', 'type' => 'string', 'category' => 'rp', 'description' => 'Straße der Organisation', 'editable' => 1, 'order' => 21],
        ['key' => 'RP_ZIP', 'value' => '1337', 'type' => 'string', 'category' => 'rp', 'description' => 'PLZ der Organisation', 'editable' => 1, 'order' => 22],

        // FUNKTIONEN
        ['key' => 'CHAR_ID', 'value' => 'true', 'type' => 'boolean', 'category' => 'funktionen', 'description' => 'Wird eine eindeutige Charakter-ID verwendet?', 'editable' => 1, 'order' => 30],
        ['key' => 'ENOTF_PREREG', 'value' => 'true', 'type' => 'boolean', 'category' => 'funktionen', 'description' => 'Wird das Voranmeldungssystem des eNOTF verwendet?', 'editable' => 1, 'order' => 31],
        ['key' => 'ENOTF_USE_PIN', 'value' => 'true', 'type' => 'boolean', 'category' => 'funktionen', 'description' => 'Wird die PIN-Funktion des eNOTF verwendet?', 'editable' => 1, 'order' => 32],
        ['key' => 'ENOTF_PIN', 'value' => '1234', 'type' => 'string', 'category' => 'funktionen', 'description' => 'PIN für den Zugang zum eNOTF (4-6 Zahlen)', 'editable' => 1, 'order' => 33],
        ['key' => 'ENOTF_REQUIRE_USER_AUTH', 'value' => 'false', 'type' => 'boolean', 'category' => 'funktionen', 'description' => 'Wird eine Registrierung/Anmeldung im Hauptsystem für den Zugang zum eNOTF vorausgesetzt?', 'editable' => 1, 'order' => 34],
        ['key' => 'FIRE_INCIDENT_REQUIRE_USER_AUTH', 'value' => 'false', 'type' => 'boolean', 'category' => 'funktionen', 'description' => 'Wird eine Registrierung/Anmeldung im Hauptsystem für die Fahrzeuganmeldung im Einsatzprotokoll vorausgesetzt?', 'editable' => 1, 'order' => 35],
        ['key' => 'REGISTRATION_MODE', 'value' => 'open', 'type' => 'string', 'category' => 'funktionen', 'description' => 'Registrierungsmodus: open = für jeden möglich, code = nur mit Code, closed = keine Registrierung', 'editable' => 1, 'order' => 36],
        ['key' => 'BASE_PATH', 'value' => '/', 'type' => 'string', 'category' => 'funktionen', 'description' => 'Basis-Pfad des Systems (z.B. /intraRP/)', 'editable' => 1, 'order' => 37],
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

    // Special handling for API_KEY to never overwrite existing value
    $apiKeyStmt = $pdo->prepare("
        INSERT INTO intra_config (config_key, config_value, config_type, category, description, is_editable, display_order)
        VALUES (:key, :value, :type, :category, :description, :editable, :order)
        ON DUPLICATE KEY UPDATE
            config_type = VALUES(config_type),
            category = VALUES(category),
            description = VALUES(description),
            is_editable = VALUES(is_editable),
            display_order = VALUES(display_order),
            config_value = config_value
    ");

    foreach ($configs as $config) {
        if ($config['key'] === 'API_KEY') {
            // Use special statement that doesn't overwrite API_KEY value
            $apiKeyStmt->execute([
                'key' => $config['key'],
                'value' => $config['value'],
                'type' => $config['type'],
                'category' => $config['category'],
                'description' => $config['description'],
                'editable' => $config['editable'],
                'order' => $config['order']
            ]);
        } else {
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
    }
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
