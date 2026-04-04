<?php
/**
 * Konvertiert ein Twig-Template in ein visuelles Editor-Layout (Canvas-JSON).
 * Nur im Development-Modus verfügbar.
 */
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../assets/config/database.php';

use App\Auth\Permissions;
use App\Security\CsrfProtection;
use App\Documents\DocumentTemplateManager;
use App\Documents\TemplateLayoutManager;
use App\Documents\TwigToCanvasConverter;

header('Content-Type: application/json');

// Nur im Development-Modus erlaubt
if (($_ENV['APP_ENV'] ?? 'production') !== 'development') {
    echo json_encode(['success' => false, 'error' => 'Nur im Development-Modus verfügbar']);
    exit;
}

if (!Permissions::check(['admin'])) {
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    CsrfProtection::requireValid($input);

    $templateId = (int) ($input['template_id'] ?? 0);
    $convertAll = !empty($input['convert_all']);

    $manager = new DocumentTemplateManager($pdo);
    $layoutManager = new TemplateLayoutManager($pdo);
    $converter = new TwigToCanvasConverter();

    if ($convertAll) {
        // Alle Templates konvertieren
        $templates = $manager->listTemplates();
        $results = ['converted' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($templates as $t) {
            try {
                $template = $manager->getTemplate((int) $t['id']);
                if (!$template || empty($template['template_file'])) {
                    $results['skipped']++;
                    continue;
                }

                // Nur konvertieren wenn noch kein visuelles Layout existiert
                $existingLayout = $layoutManager->getLayout((int) $t['id']);
                if ($existingLayout && !empty($input['overwrite'])) {
                    // Überschreiben wenn gewünscht
                } elseif ($existingLayout) {
                    $results['skipped']++;
                    continue;
                }

                $canvasJson = $converter->convert($template['template_file'], $template['fields'] ?? []);
                $layoutManager->saveLayout((int) $t['id'], json_encode($canvasJson));
                $results['converted']++;
            } catch (\Throwable $e) {
                $results['errors'][] = ($t['name'] ?? $t['id']) . ': ' . $e->getMessage();
            }
        }

        echo json_encode(['success' => true, 'results' => $results]);
    } else {
        // Einzelnes Template konvertieren
        if (!$templateId) {
            throw new \Exception('Template-ID fehlt');
        }

        $template = $manager->getTemplate($templateId);
        if (!$template) {
            throw new \Exception('Template nicht gefunden');
        }
        if (empty($template['template_file'])) {
            throw new \Exception('Keine Template-Datei vorhanden');
        }

        $canvasJson = $converter->convert($template['template_file'], $template['fields'] ?? []);
        $layoutId = $layoutManager->saveLayout($templateId, json_encode($canvasJson));

        echo json_encode([
            'success' => true,
            'layout_id' => $layoutId,
            'objects_count' => count($canvasJson['objects'] ?? []),
        ]);
    }
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
