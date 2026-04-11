<?php
/**
 * Liefert das Twig-Template als gerendertes HTML für die Browser-basierte Konvertierung.
 * Twig-Tags ({% %}) werden entfernt, {{ Platzhalter }} bleiben als Text erhalten.
 * Nur im Development-Modus verfügbar.
 */
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../assets/config/database.php';

use App\Auth\Permissions;
use App\Documents\DocumentTemplateManager;

if (!Permissions::check(['admin'])) {
    http_response_code(403);
    exit('Keine Berechtigung');
}

$templateId = (int) ($_GET['id'] ?? 0);
if (!$templateId) {
    http_response_code(400);
    exit('Template-ID fehlt');
}

$manager = new DocumentTemplateManager($pdo);
$template = $manager->getTemplate($templateId);
if (!$template || empty($template['template_file'])) {
    http_response_code(404);
    exit('Template nicht gefunden');
}

$templatePath = __DIR__ . '/../../dokumente/templates/' . $template['template_file'];
if (!file_exists($templatePath)) {
    http_response_code(404);
    exit('Template-Datei nicht gefunden: ' . htmlspecialchars($template['template_file'], ENT_QUOTES, 'UTF-8'));
}

$html = file_get_contents($templatePath);

// Twig-Kontrollstrukturen entfernen, Inhalt behalten
// {% if ... %} → entfernen, {% endif %} → entfernen, {% else %} → entfernen
$html = preg_replace('/\{%\s*(?:if|elseif|else|endif|for|endfor|block|endblock|extends|set)[^%]*%\}/', '', $html);

// {{ variable|filter }} → Text "{{ variable }}" behalten (Filter entfernen für Lesbarkeit)
$html = preg_replace('/\{\{\s*([a-zA-Z0-9_.]+)\s*\|[^}]*\}\}/', '{{ $1 }}', $html);

header('Content-Type: text/html; charset=UTF-8');
// CORS für gleiche Origin
header('X-Frame-Options: SAMEORIGIN');
echo $html;
