<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../assets/config/database.php';
require_once __DIR__ . '/../../../src/Documents/TemplateLayoutManager.php';

use App\Documents\TemplateLayoutManager;
use App\Auth\Permissions;

header('Content-Type: application/json');

if (!Permissions::check(['admin', 'personnel.documents.manage'])) {
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

try {
    $templateId = (int) ($_GET['template_id'] ?? 0);

    if (!$templateId) {
        throw new \Exception('template_id ist erforderlich');
    }

    $manager = new TemplateLayoutManager($pdo);
    $layout = $manager->getLayout($templateId);

    if (!$layout) {
        echo json_encode([
            'success' => true,
            'layout' => null,
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'layout' => [
            'id' => (int) $layout['id'],
            'version' => (int) $layout['version'],
            'canvas_json' => $layout['canvas_json'],
            'page_width_mm' => (float) $layout['page_width_mm'],
            'page_height_mm' => (float) $layout['page_height_mm'],
            'updated_at' => $layout['updated_at'],
        ],
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Interner Fehler']);
}
