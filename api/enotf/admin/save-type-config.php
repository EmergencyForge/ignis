<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../assets/config/database.php';

use App\Auth\Permissions;
use App\Enotf\ProtocolTypeService;

header('Content-Type: application/json');

if (!isset($_SESSION['userid']) || !Permissions::check(['admin', 'edivi.view'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültige Anfrage.']);
    exit();
}

$typeService = new ProtocolTypeService($pdo);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        // ──── Save Sections ────
        case 'save_sections':
            $typeId = (int)($input['type_id'] ?? 0);
            $sections = $input['sections'] ?? [];

            if (!$typeId || empty($sections)) {
                echo json_encode(['success' => false, 'error' => 'Ungültige Daten.']);
                exit();
            }

            $typeService->updateTypeSections($typeId, $sections);
            echo json_encode(['success' => true]);
            break;

        // ──── Save Fields ────
        case 'save_fields':
            $typeId = (int)($input['type_id'] ?? 0);
            $sectionId = (int)($input['section_id'] ?? 0);
            $fields = $input['fields'] ?? [];

            if (!$typeId || !$sectionId) {
                echo json_encode(['success' => false, 'error' => 'Ungültige Daten.']);
                exit();
            }

            $typeService->updateTypeFields($typeId, $sectionId, $fields);
            echo json_encode(['success' => true]);
            break;

        // ──── Add Existing Field to Section ────
        case 'add_field':
            $typeId = (int)($input['type_id'] ?? 0);
            $sectionId = (int)($input['section_id'] ?? 0);
            $fieldDefId = (int)($input['field_definition_id'] ?? 0);

            if (!$typeId || !$sectionId || !$fieldDefId) {
                echo json_encode(['success' => false, 'error' => 'Ungültige Daten.']);
                exit();
            }

            // Get current max sort order
            $existing = $typeService->getFieldsForSection($typeId, $sectionId);
            $maxSort = 0;
            foreach ($existing as $f) {
                $maxSort = max($maxSort, (int)$f['type_sort_order']);
            }

            $stmt = $pdo->prepare("INSERT IGNORE INTO intra_edivi_type_fields
                (protocol_type_id, section_id, field_definition_id, enabled, is_required, sort_order, column_width)
                VALUES (:type_id, :section_id, :field_def_id, 1, 0, :sort_order, 'full')");
            $stmt->execute([
                'type_id'      => $typeId,
                'section_id'   => $sectionId,
                'field_def_id' => $fieldDefId,
                'sort_order'   => $maxSort + 1,
            ]);

            ProtocolTypeService::clearCache();
            echo json_encode(['success' => true]);
            break;

        // ──── Create Custom Field + Add to Section ────
        case 'create_field':
            $typeId = (int)($input['type_id'] ?? 0);
            $sectionId = (int)($input['section_id'] ?? 0);
            $label = trim($input['label'] ?? '');
            $fieldType = $input['field_type'] ?? 'text';

            if (!$typeId || !$sectionId || !$label) {
                echo json_encode(['success' => false, 'error' => 'Label ist Pflichtfeld.']);
                exit();
            }

            // Generate field_key from label
            $fieldKey = 'custom_' . preg_replace('/[^a-z0-9_]/', '', str_replace(' ', '_', strtolower($label)));
            $fieldKey = substr($fieldKey, 0, 100);

            // Ensure uniqueness
            $suffix = '';
            $counter = 0;
            while ($typeService->getFieldDefinition($fieldKey . $suffix)) {
                $counter++;
                $suffix = '_' . $counter;
            }
            $fieldKey .= $suffix;

            $fieldDefId = $typeService->createFieldDefinition([
                'field_key'    => $fieldKey,
                'label'        => $label,
                'field_type'   => $fieldType,
                'options_json' => $input['options_json'] ?? null,
                'widget'       => null,
                'is_legacy_column' => 0,
                'is_core'      => 0,
                'placeholder'  => $input['placeholder'] ?? null,
                'hint_text'    => $input['hint_text'] ?? null,
                'input_suffix' => $input['input_suffix'] ?? null,
            ]);

            // Add to section
            $existing = $typeService->getFieldsForSection($typeId, $sectionId);
            $maxSort = 0;
            foreach ($existing as $f) {
                $maxSort = max($maxSort, (int)$f['type_sort_order']);
            }

            $stmt = $pdo->prepare("INSERT INTO intra_edivi_type_fields
                (protocol_type_id, section_id, field_definition_id, enabled, is_required, sort_order, column_width)
                VALUES (:type_id, :section_id, :field_def_id, 1, 0, :sort_order, 'full')");
            $stmt->execute([
                'type_id'      => $typeId,
                'section_id'   => $sectionId,
                'field_def_id' => $fieldDefId,
                'sort_order'   => $maxSort + 1,
            ]);

            ProtocolTypeService::clearCache();
            echo json_encode(['success' => true, 'field_key' => $fieldKey, 'field_def_id' => $fieldDefId]);
            break;

        // ──── Create Section ────
        case 'create_section':
            $typeId = (int)($input['type_id'] ?? 0);
            $name = trim($input['name'] ?? '');

            if (!$typeId || !$name) {
                echo json_encode(['success' => false, 'error' => 'Name ist Pflichtfeld.']);
                exit();
            }

            $slug = preg_replace('/[^a-z0-9_-]/', '', str_replace(' ', '_', strtolower($name)));

            $sectionId = $typeService->createSection([
                'slug' => $slug,
                'name' => $name,
                'icon' => $input['icon'] ?? null,
            ]);

            // Add to type with next sort order
            $existingSections = $typeService->getSectionsForType($typeId);
            $maxSort = count($existingSections);

            $stmt = $pdo->prepare("INSERT INTO intra_edivi_type_sections
                (protocol_type_id, section_id, enabled, sort_order, is_required)
                VALUES (:type_id, :section_id, 1, :sort_order, 0)");
            $stmt->execute([
                'type_id'    => $typeId,
                'section_id' => $sectionId,
                'sort_order' => $maxSort + 1,
            ]);

            ProtocolTypeService::clearCache();
            echo json_encode(['success' => true, 'section_id' => $sectionId]);
            break;

        // ──── Create Validation Rule ────
        case 'create_rule':
            $typeId = (int)($input['type_id'] ?? 0);
            if (!$typeId) {
                echo json_encode(['success' => false, 'error' => 'Ungültiger Typ.']);
                exit();
            }

            $ruleId = $typeService->createValidationRule([
                'protocol_type_id' => $typeId,
                'name'             => $input['name'] ?? '',
                'rule_json'        => $input['rule_json'] ?? '{}',
                'error_message'    => $input['error_message'] ?? '',
                'severity'         => $input['severity'] ?? 'error',
                'sort_order'       => (int)($input['sort_order'] ?? 0),
            ]);

            echo json_encode(['success' => true, 'rule_id' => $ruleId]);
            break;

        // ──── Delete Validation Rule ────
        case 'delete_rule':
            $ruleId = (int)($input['rule_id'] ?? 0);
            if ($ruleId) {
                $typeService->deleteValidationRule($ruleId);
            }
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unbekannte Aktion: ' . $action]);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
