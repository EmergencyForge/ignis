<?php
/**
 * Migriert alle bestehenden Twig-Templates zu visuellen JSON-Layouts.
 * Voraussetzung: Die Tabellen aus create_intra_dokument_template_layouts_17032026.php
 * und alter_intra_dokument_templates_17032026.php müssen bereits existieren.
 */

require_once __DIR__ . '/../../src/Documents/TemplateLayoutManager.php';
require_once __DIR__ . '/../../src/Documents/TemplateAssetManager.php';
require_once __DIR__ . '/../../src/Documents/TwigToVisualMigrator.php';

use App\Documents\TwigToVisualMigrator;

try {
    $migrator = new TwigToVisualMigrator($pdo);
    $results = $migrator->migrateAll();

    foreach ($results as $result) {
        if ($result['status'] === 'ok') {
            echo "OK: Template '{$result['name']}' (ID {$result['id']}) migriert\n";
        } else {
            echo "FEHLER: Template '{$result['name']}' (ID {$result['id']}): {$result['error']}\n";
        }
    }

    echo "\nMigration abgeschlossen: " . count(array_filter($results, fn($r) => $r['status'] === 'ok')) . " von " . count($results) . " Templates erfolgreich migriert.\n";
} catch (Exception $e) {
    echo "Migration fehlgeschlagen: " . $e->getMessage() . "\n";
}
