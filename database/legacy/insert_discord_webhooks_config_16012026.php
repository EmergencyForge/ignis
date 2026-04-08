<?php
try {
    // Discord Webhook Konfigurationen hinzufügen
    $configs = [
        [
            'key' => 'DISCORD_WEBHOOK_ENOTF_PROTOCOL',
            'value' => '',
            'type' => 'string',
            'category' => 'integrationen',
            'description' => 'Discord Webhook URL für freigegebene eNOTF Protokolle',
            'editable' => 1,
            'order' => 100
        ],
        [
            'key' => 'DISCORD_WEBHOOK_FIRE_PROTOCOL',
            'value' => '',
            'type' => 'string',
            'category' => 'integrationen',
            'description' => 'Discord Webhook URL für freigegebene Feuerwehr (fireTab) Protokolle',
            'editable' => 1,
            'order' => 101
        ],
        [
            'key' => 'DISCORD_WEBHOOK_ENOTF_PREREG',
            'value' => '',
            'type' => 'string',
            'category' => 'integrationen',
            'description' => 'Discord Webhook URL für neue Voranmeldungen im eNOTF',
            'editable' => 1,
            'order' => 102
        ]
    ];

    foreach ($configs as $config) {
        // Prüfen ob Config bereits existiert
        $stmt = $pdo->prepare("SELECT id FROM intra_config WHERE config_key = ?");
        $stmt->execute([$config['key']]);

        if (!$stmt->fetch()) {
            // Config hinzufügen
            $stmt = $pdo->prepare("
                INSERT INTO intra_config (config_key, config_value, config_type, category, description, is_editable, display_order)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $config['key'],
                $config['value'],
                $config['type'],
                $config['category'],
                $config['description'],
                $config['editable'],
                $config['order']
            ]);
        }
    }
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
