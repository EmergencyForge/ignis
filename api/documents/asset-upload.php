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
    // CSRF-Token kommt bei FormData über POST-Feld
    CsrfProtection::requireValid(null);

    if (empty($_FILES['file'])) {
        throw new \Exception('Keine Datei hochgeladen');
    }

    $templateId = isset($_POST['template_id']) ? (int) $_POST['template_id'] : null;
    $assetType = $_POST['asset_type'] ?? 'image';

    $manager = new TemplateAssetManager($pdo);
    $result = $manager->upload($_FILES['file'], $templateId, $assetType);

    echo json_encode([
        'success' => true,
        'asset' => $result,
        'csrf_token' => CsrfProtection::getResponseToken(),
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
