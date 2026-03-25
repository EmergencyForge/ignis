<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../assets/config/database.php';
require_once __DIR__ . '/../../src/Documents/TemplateLayoutManager.php';

use App\Documents\TemplateLayoutManager;
use App\Auth\Permissions;

header('Content-Type: application/json');

if (!Permissions::check(['admin', 'personnel.documents.manage'])) {
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['template_id']) || empty($input['canvas_json'])) {
        throw new \Exception('template_id und canvas_json sind erforderlich');
    }

    $manager = new TemplateLayoutManager($pdo);

    $layoutId = $manager->saveLayout(
        (int) $input['template_id'],
        is_string($input['canvas_json']) ? $input['canvas_json'] : json_encode($input['canvas_json']),
        $input['page_width_mm'] ?? null,
        $input['page_height_mm'] ?? null
    );

    $layout = $manager->getLayoutById($layoutId);

    echo json_encode([
        'success' => true,
        'layout_id' => $layoutId,
        'version' => $layout['version'] ?? 1,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
