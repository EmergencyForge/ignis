<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../assets/config/database.php';
require_once __DIR__ . '/../../src/Documents/DocumentTemplateManager.php';

use App\Documents\DocumentTemplateManager;
use App\Auth\Permissions;
use App\Helpers\Flash;

header('Content-Type: application/json');

if (!Permissions::check(['admin', 'personnel.documents.manage'])) {
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $manager = new DocumentTemplateManager($pdo);

    $isUpdate = isset($input['id']) && $input['id'];
    $fieldsChanged = false;

    if ($isUpdate) {
        // Prüfe ob Felder hinzugefügt oder geändert wurden
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM intra_dokument_template_fields WHERE template_id = ?");
        $stmt->execute([$input['id']]);
        $oldFieldCount = $stmt->fetchColumn();
        $newFieldCount = count($input['fields']);

        $fieldsChanged = ($newFieldCount !== $oldFieldCount);

        $manager->updateTemplate($input['id'], [
            'name' => $input['name'],
            'category' => $input['category'] ?? null,
            'category_id' => $input['category_id'] ?? null,
            'description' => $input['description'] ?? null,
            'template_file' => $input['template_file'] ?? null,
            'editor_type' => $input['editor_type'] ?? 'visual',
        ]);

        // Lösche alte Felder
        $stmt = $pdo->prepare("DELETE FROM intra_dokument_template_fields WHERE template_id = :id");
        $stmt->execute(['id' => $input['id']]);

        $templateId = $input['id'];
    } else {
        // Create
        $templateFile = $input['template_file'] ?? strtolower(str_replace(' ', '_', $input['name'])) . '.html.twig';

        $templateId = $manager->createTemplate([
            'name' => $input['name'],
            'category' => $input['category'] ?? null,
            'category_id' => $input['category_id'] ?? null,
            'description' => $input['description'] ?? null,
            'template_file' => $templateFile,
            'editor_type' => $input['editor_type'] ?? 'visual',
        ]);
    }

    // Füge Felder hinzu
    foreach ($input['fields'] as $field) {
        $field['is_required'] = !empty($field['is_required']) ? 1 : 0;
        $manager->addField($templateId, $field);
    }

    // Speichere Config mit geschlechtsspezifischen Labels
    $config = buildTemplateConfig($input['fields']);
    $stmt = $pdo->prepare("UPDATE intra_dokument_templates SET config = ? WHERE id = ?");
    $stmt->execute([json_encode($config), $templateId]);

    // Erstelle Template-Datei NUR wenn sie noch nicht existiert
    $templateFile = $input['template_file'] ?? strtolower(str_replace(' ', '_', $input['name'])) . '.html.twig';
    $templateCreated = createTemplateFile($templateId, $input);

    // Setze passende Flash-Nachricht
    if ($templateCreated) {
        Flash::success('Template erfolgreich erstellt');
    } elseif ($fieldsChanged) {
        Flash::warning(
            'Template wurde aktualisiert, aber die Felder wurden geändert.<br><br>' .
                '<strong>Wichtig:</strong> Die Template-Datei <code>' . htmlspecialchars($templateFile) . '</code> wurde nicht automatisch aktualisiert.<br><br>' .
                '<strong>Optionen:</strong><br>' .
                '<ul style="margin: 8px 0; padding-left: 20px;">' .
                '<li>Löschen Sie die Datei <code>dokumente/templates/' . htmlspecialchars($templateFile) . '</code> und speichern Sie erneut, um sie automatisch zu generieren</li>' .
                '<li>Oder passen Sie die bestehende Datei manuell an die neuen Felder an</li>' .
                '</ul>',
            'Template aktualisiert - Aktion erforderlich!'
        );
    } else {
        Flash::success('Template erfolgreich aktualisiert');
    }

    echo json_encode([
        'success' => true,
        'id' => $templateId,
        'template_created' => $templateCreated,
        'fields_changed' => $fieldsChanged && !$templateCreated
    ]);
} catch (\Throwable $e) {
    Flash::error($e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Interner Fehler']);
}

/**
 * @param array<int, array<string, mixed>> $fields
 * @return array<string, mixed>
 */
function buildTemplateConfig(array $fields): array
{
    $config = ['fields' => []];

    foreach ($fields as $field) {
        if ($field['field_type'] === 'select' && !empty($field['field_options'])) {
            $config['fields'][$field['field_name']] = [
                'type' => 'select',
                'label' => $field['field_label'],
                'options' => $field['field_options'],
                'gender_specific' => $field['gender_specific'] ?? false
            ];
        }
    }

    return $config;
}

/**
 * @param array<string, mixed> $data
 */
function createTemplateFile(int $templateId, array $data): bool
{
    $templatePath = __DIR__ . '/../../dokumente/templates/';
    if (!file_exists($templatePath)) {
        mkdir($templatePath, 0755, true);
    }

    $filename = $data['template_file'] ?? strtolower(str_replace(' ', '_', $data['name'])) . '.html.twig';
    $filepath = $templatePath . $filename;

    // WICHTIG: Prüfe ob Datei bereits existiert
    if (file_exists($filepath)) {
        error_log("Template-Datei existiert bereits: {$filepath} - wird nicht überschrieben");
        return false; // Datei wurde NICHT erstellt/überschrieben
    }

    $twig = generateTwigTemplate($data);
    file_put_contents($filepath, $twig);

    return true; // Datei wurde neu erstellt
}

/**
 * @param array<string, mixed> $data
 */
function generateTwigTemplate(array $data): string
{
    $template = <<<'TWIG'
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

    foreach ($data['fields'] as $field) {
        $fieldName = $field['field_name'];

        if ($field['field_type'] === 'richtext' || $field['field_type'] === 'textarea') {
            $template .= "        <div class=\"field-section\">\n";
            $template .= "            <strong>{$field['field_label']}:</strong>\n";
            $template .= "            <div class=\"field-box\">{{ {$fieldName}|raw }}</div>\n";
            $template .= "        </div>\n";
        } else {
            $template .= "        <p><strong>{$field['field_label']}:</strong> {{ {$fieldName} }}</p>\n";
        }
    }

    $template .= <<<'TWIG'
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

    return $template;
}
