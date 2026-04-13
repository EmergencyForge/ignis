<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../assets/config/database.php';
require_once __DIR__ . '/../../../src/Documents/DocumentTemplateManager.php';

use App\Documents\DocumentTemplateManager;

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

try {
    $manager = new DocumentTemplateManager($pdo);
    $category = $_GET['category'] ?? null;

    $templates = $manager->listTemplates($category);
    echo json_encode($templates);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Interner Fehler']);
}
