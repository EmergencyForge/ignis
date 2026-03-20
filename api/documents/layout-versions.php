<?php
/**
 * Layout-Versionen API
 * GET:  ?template_id=N           — Versionen auflisten
 * POST: {template_id, layout_id} — Version wiederherstellen
 */

require_once __DIR__ . '/../../assets/config/config.php';

header('Content-Type: application/json');

if (!Permissions::check(['admin', 'personnel.documents.manage'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Keine Berechtigung']);
    exit;
}

$layoutManager = new \App\Documents\TemplateLayoutManager($pdo);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $templateId = (int)($_GET['template_id'] ?? 0);
        if (!$templateId) {
            throw new \InvalidArgumentException('template_id fehlt');
        }

        $versions = $layoutManager->getLayoutVersions($templateId);
        echo json_encode(['success' => true, 'versions' => $versions]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $templateId = (int)($input['template_id'] ?? 0);
        $layoutId = (int)($input['layout_id'] ?? 0);

        if (!$templateId || !$layoutId) {
            throw new \InvalidArgumentException('template_id und layout_id benötigt');
        }

        $success = $layoutManager->restoreVersion($templateId, $layoutId);
        echo json_encode(['success' => $success]);
    }
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
