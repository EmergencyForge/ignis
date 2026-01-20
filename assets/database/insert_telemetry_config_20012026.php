<?php

/**
 * Migration: Telemetrie-Konfiguration hinzufügen
 */

if (!isset($pdo)) {
    throw new Exception('PDO connection required');
}

$stmt = $pdo->query("SELECT config_key FROM intra_config WHERE config_key = 'TELEMETRY_ENABLED'");
if ($stmt->rowCount() > 0) {
    echo "Telemetrie-Konfiguration bereits vorhanden.\n";
    return;
}

$configs = [
    ['TELEMETRY_ENABLED', 'true', 'boolean', 'telemetrie', 'Anonymisierte Statistiken senden', 10],
    ['ANNOUNCEMENTS_ENABLED', 'true', 'boolean', 'telemetrie', 'Globale Benachrichtigungen empfangen', 20],
    ['HUB_URL', 'https://emergencyforge.de', 'url', 'telemetrie', 'URL des intraRP-Hub-Servers', 30],
];

$stmt = $pdo->prepare("
    INSERT INTO intra_config 
    (config_key, config_value, config_type, category, description, is_editable, display_order)
    VALUES (?, ?, ?, ?, ?, 1, ?)
");

foreach ($configs as $c) {
    $stmt->execute($c);
}

echo "Telemetrie-Konfiguration hinzugefügt.\n";
