<?php
/**
 * Regeneriert die Twig-Template-Datei aus der Template-Definition (Felder).
 * Überschreibt die bestehende .html.twig-Datei mit einer neu generierten Version.
 */
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../assets/config/database.php';

use App\Auth\Permissions;
use App\Security\CsrfProtection;
use App\Documents\DocumentTemplateManager;

header('Content-Type: application/json');

if (!Permissions::check(['admin'])) {
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    CsrfProtection::requireValid($input);

    $templateId = (int) ($input['template_id'] ?? 0);
    if (!$templateId) {
        throw new \Exception('Template-ID fehlt');
    }

    $manager = new DocumentTemplateManager($pdo);
    $template = $manager->getTemplate($templateId);
    if (!$template) {
        throw new \Exception('Template nicht gefunden');
    }

    $templatePath = __DIR__ . '/../../../dokumente/templates/';
    if (!is_dir($templatePath)) {
        mkdir($templatePath, 0755, true);
    }

    $filename = $template['template_file']
        ?? strtolower(str_replace(' ', '_', $template['name'])) . '.html.twig';
    $filepath = $templatePath . $filename;

    $twig = generateTemplateHtml($template);
    file_put_contents($filepath, $twig);

    echo json_encode([
        'success' => true,
        'message' => 'Template-Datei wurde neu generiert',
        'file' => $filename,
        'csrf_token' => CsrfProtection::getResponseToken(),
    ]);
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Interner Fehler']);
}

function generateTemplateHtml(array $template): string
{
    $html = <<<'TWIG'
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>{{ SYSTEM_NAME }}</title>
    <style>
        @page {
            margin: 0;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            margin: 0;
            padding: 20mm 25mm;
            font-size: 11pt;
            line-height: 1.4;
        }

        .header {
            margin-bottom: 8mm;
        }

        .header-right {
            float: right;
            width: 35%;
            text-align: right;
        }

        .header-left {
            width: 60%;
            font-size: 10pt;
            line-height: 1.3;
        }

        .logo-placeholder {
            padding: 5mm 2.5mm;
            text-align: center;
            font-size: 9pt;
            color: #666;
            margin-bottom: 4mm;
        }

        .date-box {
            margin-top: 4mm;
        }

        .date-label {
            font-size: 10pt;
            margin-bottom: 2mm;
        }

        .date-value {
            font-size: 12pt;
            font-weight: bold;
        }

        .recipient {
            margin: 10mm 0;
            font-size: 11pt;
            line-height: 1.5;
        }

        .title {
            font-size: 15pt;
            font-weight: bold;
            margin: 12mm 0 8mm 0;
        }

        .letter-content {
            font-size: 11pt;
            line-height: 1.6;
        }

        .letter-content p {
            margin: 4mm 0;
        }

        .field-section {
            margin: 4mm 0;
        }

        .field-box {
            border: 1px solid #ccc;
            padding: 3mm;
            margin: 4mm 0;
            min-height: 20mm;
        }

        .date-location {
            margin-top: 12mm;
            font-size: 10pt;
        }

        .document-reference {
            margin-top: 4mm;
            font-size: 9pt;
            color: #333;
        }

        .issuer-info {
            margin-top: 6mm;
            font-size: 10pt;
        }

        .electronic-note {
            margin-top: 2mm;
            font-size: 8pt;
            font-style: italic;
            color: #666;
        }

        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }
    </style>
</head>

<body>
    <div class="header clearfix">
        <div class="header-right">
            <div class="logo-placeholder">
                {% if logo_base64 %}
                <img src="{{ logo_base64 }}" alt="Logo" style="max-width: 100%;">
                {% endif %}
            </div>

            <div class="date-box">
                <div class="date-label">Datum</div>
                <div class="date-value">{{ ausstellungsdatum }}</div>
            </div>
        </div>

        <div class="header-left">
            {{ RP_ORGTYPE }} {{ SERVER_CITY }}<br>
            {{ RP_STREET }}<br>
            {{ RP_ZIP }} {{ SERVER_CITY }}
        </div>
    </div>

    <div style="clear: both;"></div>

    <div class="recipient">
        {{ anrede_text }}<br>
        {{ erhalter }}<br>
        {{ RP_ZIP }} {{ SERVER_CITY }}
    </div>

    <div class="title">Dokument</div>

    <div class="letter-content">

TWIG;

    $fields = $template['fields'] ?? [];
    foreach ($fields as $field) {
        $fieldName = $field['field_name'];
        $fieldLabel = $field['field_label'];

        if (in_array($field['field_type'], ['richtext', 'textarea'])) {
            $html .= "        <div class=\"field-section\">\n";
            $html .= "            <strong>{$fieldLabel}:</strong>\n";
            $html .= "            <div class=\"field-box\">{{ {$fieldName}|raw }}</div>\n";
            $html .= "        </div>\n";
        } else {
            $html .= "        <p><strong>{$fieldLabel}:</strong> {{ {$fieldName} }}</p>\n";
        }
    }

    $html .= <<<'TWIG'
    </div>

    <div class="date-location">
        {{ SERVER_CITY }}, den {{ ausstellungsdatum }}
    </div>

    <div class="document-reference">
        <strong>Ihr Zeichen:</strong> {{ document_id }}
    </div>

    <div class="issuer-info">
        <strong>{{ issuer.fullname }}</strong><br>
        {{ issuer.dienstgrad_text }}
        {% if issuer.zusatz %}<br>{{ issuer.zusatz }}{% endif %}
    </div>

    <div class="electronic-note">
        — Dieses Dokument wurde elektronisch erstellt und ist ohne Unterschrift gültig. —
    </div>
</body>

</html>
TWIG;

    return $html;
}
