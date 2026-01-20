#!/usr/bin/env php
<?php
/**
 * Cron-Job für Telemetrie und Announcements
 * 
 * Empfohlene Crontab-Einstellung (täglich um 3:00 Uhr):
 * 0 3 * * * /usr/bin/php /path/to/cron/telemetry-cron.php
 */

// CLI-Check
if (php_sapi_name() !== 'cli') {
    die("Dieses Script muss via CLI ausgeführt werden.\n");
}

// Bootstrap
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../assets/config/database.php';
require_once __DIR__ . '/../src/Telemetry/TelemetryManager.php';
require_once __DIR__ . '/../src/Telemetry/GlobalAnnouncementManager.php';

use App\Telemetry\TelemetryManager;
use App\Telemetry\GlobalAnnouncementManager;

echo "[" . date('Y-m-d H:i:s') . "] Telemetrie-Cron gestartet\n";

// 1. Telemetrie-Heartbeat (falls aktiviert)
$telemetry = new TelemetryManager($pdo);

if ($telemetry->isEnabled()) {
    if ($telemetry->shouldSendHeartbeat()) {
        echo "Sende Telemetrie-Heartbeat...\n";
        $result = $telemetry->sendHeartbeat();
        echo "Ergebnis: " . $result['message'] . "\n";
    } else {
        echo "Telemetrie: Heartbeat noch nicht fällig.\n";
    }
} else {
    echo "Telemetrie ist deaktiviert.\n";
}

// 2. Announcements-Cache aktualisieren
$announcements = new GlobalAnnouncementManager($pdo);

if ($announcements->isEnabled()) {
    echo "Aktualisiere Announcements-Cache...\n";
    $result = $announcements->refreshCache();
    echo "Ergebnis: " . $result['message'] . "\n";
} else {
    echo "Announcements sind deaktiviert.\n";
}

// 3. Alte Dismissals aufräumen
echo "Räume alte Dismissals auf...\n";
$deleted = $announcements->cleanupOldDismissals(90);
echo "Gelöscht: {$deleted} alte Einträge\n";

echo "[" . date('Y-m-d H:i:s') . "] Cron abgeschlossen\n";
