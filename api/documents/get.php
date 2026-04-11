<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../assets/config/database.php';
require_once __DIR__ . '/../../src/Documents/DocumentTemplateManager.php';

use App\Documents\DocumentTemplateManager;

header('Content-Type: application/json');

try {
    $id = $_GET['id'] ?? null;

    if (!$id) {
        throw new Exception('Template-ID fehlt');
    }

    $manager = new DocumentTemplateManager($pdo);
    $template = $manager->getTemplate($id);

    if (!$template) {
        throw new Exception('Template nicht gefunden');
    }

    foreach ($template['fields'] as &$field) {
        if ($field['field_type'] === 'db_dg') {
            $stmt = $pdo->query("SELECT id, name, name_m, name_w FROM intra_mitarbeiter_dienstgrade WHERE archive = 0 ORDER BY priority ASC");
            $field['field_options'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $field['field_options'] = array_map(function ($item) {
                return [
                    'value' => $item['id'],
                    'label' => $item['name'],
                    'label_m' => $item['name_m'],
                    'label_w' => $item['name_w']
                ];
            }, $field['field_options']);
        }

        if ($field['field_type'] === 'db_rdq') {
            $stmt = $pdo->query("SELECT id, name, name_m, name_w FROM intra_mitarbeiter_rdquali WHERE trainable = 1 AND none = 0 ORDER BY priority ASC");
            $field['field_options'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $field['field_options'] = array_map(function ($item) {
                return [
                    'value' => $item['id'],
                    'label' => $item['name'],
                    'label_m' => $item['name_m'],
                    'label_w' => $item['name_w']
                ];
            }, $field['field_options']);
        }
    }

    echo json_encode($template);
} catch (Exception $e) {
    http_response_code(404);
    echo json_encode(['error' => 'Interner Fehler']);
}
