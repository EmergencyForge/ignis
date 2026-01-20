<?php

/**
 * Debug: Announcements testen
 * Nach Verwendung löschen!
 */

require_once __DIR__ . '/assets/config/config.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/assets/config/database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Announcements Debug ===\n\n";

// 1. Direkte DB-Abfrage
echo "1. Direkte DB-Abfrage (Cache-Tabelle):\n";
try {
    $stmt = $pdo->query("SELECT * FROM intra_global_announcements_cache");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   Anzahl: " . count($rows) . "\n";
    foreach ($rows as $row) {
        echo "   - ID: {$row['announcement_id']}, Title: {$row['title']}, Type: {$row['type']}\n";
        echo "     valid_from: " . ($row['valid_from'] ?? 'NULL') . "\n";
        echo "     valid_until: " . ($row['valid_until'] ?? 'NULL') . "\n";
        echo "     admin_only: " . ($row['admin_only'] ?? 'NULL') . "\n";
    }
} catch (Exception $e) {
    echo "   FEHLER: " . $e->getMessage() . "\n";
}

echo "\n2. GlobalAnnouncementManager laden:\n";
try {
    require_once __DIR__ . '/src/Telemetry/GlobalAnnouncementManager.php';
    $manager = new \App\Telemetry\GlobalAnnouncementManager($pdo);
    echo "   OK\n";

    echo "\n3. isEnabled():\n";
    echo "   " . ($manager->isEnabled() ? 'true' : 'false') . "\n";

    echo "\n4. getCacheInfo():\n";
    $info = $manager->getCacheInfo();
    print_r($info);

    echo "\n5. getAllCached():\n";
    $all = $manager->getAllCached();
    echo "   Anzahl: " . count($all) . "\n";
    print_r($all);

    echo "\n6. getActiveAnnouncements(null, true):\n";
    $active = $manager->getActiveAnnouncements(null, true);
    echo "   Anzahl: " . count($active) . "\n";
    print_r($active);
} catch (Exception $e) {
    echo "   FEHLER: " . $e->getMessage() . "\n";
    echo "   Stack: " . $e->getTraceAsString() . "\n";
}

echo "\n\n⚠️ Diese Datei jetzt löschen!";
