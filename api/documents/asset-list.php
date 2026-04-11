<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../assets/config/database.php';
require_once __DIR__ . '/../../src/Documents/TemplateAssetManager.php';

use App\Documents\TemplateAssetManager;
use App\Auth\Permissions;

header('Content-Type: application/json');

if (!Permissions::check(['admin', 'personnel.documents.manage'])) {
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

try {
    $templateId = isset($_GET['template_id']) ? (int) $_GET['template_id'] : null;

    $manager = new TemplateAssetManager($pdo);
    $assets = $manager->listAssets($templateId);

    echo json_encode([
        'success' => true,
        'assets' => $assets,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Interner Fehler']);
}
