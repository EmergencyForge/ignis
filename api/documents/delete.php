<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../assets/config/database.php';
require_once __DIR__ . '/../../src/Documents/DocumentTemplateManager.php';

use App\Documents\DocumentTemplateManager;
use App\Auth\Permissions;

header('Content-Type: application/json');

if (!Permissions::check(['admin', 'personnel.documents.manage'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

try {
    $id = $_GET['id'] ?? null;

    if (!$id) {
        throw new Exception('Template-ID fehlt');
    }

    // Lösche zuerst die Felder
    $stmt = $pdo->prepare("DELETE FROM intra_dokument_template_fields WHERE template_id = :id");
    $stmt->execute(['id' => $id]);

    // Dann das Template
    $manager = new DocumentTemplateManager($pdo);
    $success = $manager->deleteTemplate($id);

    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
