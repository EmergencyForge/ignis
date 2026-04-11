<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../assets/config/database.php';

use App\Documents\DocumentTemplateManager;
use App\Auth\Permissions;
use App\Security\CsrfProtection;

header('Content-Type: application/json');

if (!Permissions::check(['admin', 'personnel.documents.manage'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    CsrfProtection::requireValid($input);

    $sourceId = (int) ($input['template_id'] ?? 0);
    if (!$sourceId) {
        throw new \Exception('template_id ist erforderlich');
    }

    $manager = new DocumentTemplateManager($pdo);
    $newId = $manager->duplicateTemplate($sourceId);

    echo json_encode([
        'success' => true,
        'template_id' => $newId,
        'csrf_token' => CsrfProtection::getResponseToken(),
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Interner Fehler']);
}
