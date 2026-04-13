<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../assets/config/database.php';

use App\Documents\VisualTemplateRenderer;
use App\Auth\Permissions;
use App\Security\CsrfProtection;

if (!Permissions::check(['admin', 'personnel.documents.manage'])) {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<html><body style="font-family:sans-serif;color:red;padding:2rem;">Keine Berechtigung</body></html>';
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    CsrfProtection::requireValid($input);

    if (empty($input['template_id'])) {
        throw new \Exception('template_id ist erforderlich');
    }

    $renderer = new VisualTemplateRenderer($pdo);

    // Beispieldaten für die Vorschau
    $sampleData = $input['sample_data'] ?? [];

    $html = $renderer->renderPreview(
        (int) $input['template_id'],
        $sampleData,
        $input['canvas_json'] ?? null
    );

    $format = $input['format'] ?? 'html';

    if ($format === 'pdf') {
        // PDF-Direktvorschau über dompdf
        $dompdf = new \Dompdf\Dompdf([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
            'defaultPaperSize' => 'A4',
            'defaultPaperOrientation' => 'portrait',
            'dpi' => 150,
            'fontDir' => __DIR__ . '/../../../storage/fonts/',
            'fontCache' => __DIR__ . '/../../../storage/fonts/',
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="preview.pdf"');
        echo $dompdf->output();
    } else {
        // HTML direkt zurückgeben für iframe
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
    }
} catch (Exception $e) {
    // Fehler als HTML im iframe anzeigen
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:2rem;">';
    echo '<h3 style="color:#dc3545;">Vorschau-Fehler</h3>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    if (defined('APP_DEBUG') && APP_DEBUG) {
        echo '<pre style="font-size:0.8rem;color:#666;">' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    }
    echo '</body></html>';
}
