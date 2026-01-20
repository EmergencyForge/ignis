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

// is_editable = 0 damit die Einstellungen NICHT in /settings/system/config.php erscheinen
// Verwaltung erfolgt ausschließlich über /settings/system/telemetry.php
$stmt = $pdo->prepare("
    INSERT INTO intra_config 
    (config_key, config_value, config_type, category, description, is_editable, display_order)
    VALUES (?, ?, ?, ?, ?, 0, ?)
");

foreach ($configs as $c) {
    $stmt->execute($c);
}

echo "Telemetrie-Konfiguration hinzugefügt.\n";

// Für bestehende Installationen: is_editable auf 0 setzen
$pdo->exec("
    UPDATE intra_config 
    SET is_editable = 0 
    WHERE config_key IN ('TELEMETRY_ENABLED', 'ANNOUNCEMENTS_ENABLED', 'HUB_URL', 'INSTALLATION_ID', 'TELEMETRY_LAST_HEARTBEAT')
");
