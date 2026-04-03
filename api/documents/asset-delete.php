<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../assets/config/database.php';
require_once __DIR__ . '/../../src/Documents/TemplateAssetManager.php';

use App\Documents\TemplateAssetManager;
use App\Auth\Permissions;
use App\Security\CsrfProtection;

header('Content-Type: application/json');

if (!Permissions::check(['admin', 'personnel.documents.manage'])) {
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    CsrfProtection::requireValid($input);

    $assetId = (int) ($input['id'] ?? 0);

    if (!$assetId) {
        throw new \Exception('Asset-ID ist erforderlich');
    }

    $manager = new TemplateAssetManager($pdo);
    $result = $manager->delete($assetId);

    if (!$result) {
        throw new \Exception('Asset nicht gefunden');
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
