<?php

namespace App\Enotf;

use PDO;

class PresetService
{
    private PDO $pdo;
    private ProtocolTypeService $typeService;

    public function __construct(PDO $pdo, ?ProtocolTypeService $typeService = null)
    {
        $this->pdo = $pdo;
        $this->typeService = $typeService ?? new ProtocolTypeService($pdo);
    }

    /**
     * Get all presets.
     */
    public function getAll(): array
    {
        return $this->pdo->query("SELECT id, name, description, is_builtin, version, created_at FROM intra_edivi_presets ORDER BY is_builtin DESC, name ASC")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a single preset.
     */
    public function get(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM intra_edivi_presets WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Export a protocol type's full configuration as a preset JSON.
     */
    public function export(int $protocolTypeId): ?array
    {
        $type = $this->typeService->getType($protocolTypeId);
        if (!$type) {
            return null;
        }

        $sections = $this->typeService->getSectionsForType($protocolTypeId);
        $allFields = $this->typeService->getAllFieldsForType($protocolTypeId);
        $rules = $this->typeService->getValidationRules($protocolTypeId);

        // Group fields by section
        $fieldsBySection = [];
        foreach ($allFields as $field) {
            $sid = $field['section_id'];
            if (!isset($fieldsBySection[$sid])) {
                $fieldsBySection[$sid] = [];
            }
            $fieldsBySection[$sid][] = [
                'field_key'      => $field['field_key'],
                'label'          => $field['label'],
                'field_type'     => $field['field_type'],
                'options_json'   => $field['options_json'],
                'widget'         => $field['widget'],
                'is_legacy_column' => (int)$field['is_legacy_column'],
                'is_core'        => (int)$field['is_core'],
                'input_suffix'   => $field['input_suffix'],
                'is_required'    => (int)$field['is_required'],
                'sort_order'     => (int)$field['type_sort_order'],
                'column_width'   => $field['column_width'],
                'group_key'      => $field['group_key'],
                'group_label'    => $field['group_label'],
            ];
        }

        // Build sections array
        $sectionExport = [];
        foreach ($sections as $s) {
            $sectionExport[] = [
                'slug'               => $s['slug'],
                'name'               => $s['name'],
                'icon'               => $s['icon'],
                'sort_order'         => (int)$s['type_sort_order'],
                'is_required'        => (int)$s['section_required'],
                'component_template' => $s['component_template'],
                'fields'             => $fieldsBySection[$s['id']] ?? [],
            ];
        }

        // Build rules array
        $ruleExport = [];
        foreach ($rules as $r) {
            $ruleExport[] = [
                'name'          => $r['name'],
                'rule_json'     => $r['rule_json'],
                'error_message' => $r['error_message'],
                'severity'      => $r['severity'],
                'sort_order'    => (int)$r['sort_order'],
            ];
        }

        return [
            'version'       => '1.0',
            'exported_at'   => date('c'),
            'protocol_type' => [
                'slug'       => $type['slug'],
                'name'       => $type['name'],
                'short_name' => $type['short_name'],
                'color'      => $type['color'],
                'icon'       => $type['icon'],
            ],
            'sections'         => $sectionExport,
            'validation_rules' => $ruleExport,
        ];
    }

    /**
     * Save a preset from an export.
     */
    public function save(string $name, string $description, array $exportData, ?int $createdBy = null): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO intra_edivi_presets
            (name, description, is_builtin, preset_json, version, created_by)
            VALUES (:name, :desc, 0, :json, :version, :created_by)");

        $stmt->execute([
            'name'       => $name,
            'desc'       => $description,
            'json'       => json_encode($exportData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'version'    => $exportData['version'] ?? '1.0',
            'created_by' => $createdBy,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Apply a preset to a protocol type.
     * This replaces the type's section and field configuration.
     */
    public function apply(int $presetId, int $targetTypeId): bool
    {
        $preset = $this->get($presetId);
        if (!$preset) {
            return false;
        }

        $config = json_decode($preset['preset_json'], true);
        if (!$config || empty($config['sections'])) {
            return false;
        }

        $this->pdo->beginTransaction();

        try {
            // 1. Ensure all sections exist
            $sectionMap = []; // slug -> id
            $allSections = $this->typeService->getAllSections();
            foreach ($allSections as $s) {
                $sectionMap[$s['slug']] = (int)$s['id'];
            }

            foreach ($config['sections'] as $sectionData) {
                if (!isset($sectionMap[$sectionData['slug']])) {
                    $newId = $this->typeService->createSection([
                        'slug'               => $sectionData['slug'],
                        'name'               => $sectionData['name'],
                        'icon'               => $sectionData['icon'] ?? null,
                        'has_subsections'    => 0,
                        'component_template' => $sectionData['component_template'] ?? null,
                    ]);
                    $sectionMap[$sectionData['slug']] = $newId;
                }
            }

            // 2. Update type-sections
            $typeSections = [];
            foreach ($config['sections'] as $sectionData) {
                $typeSections[] = [
                    'section_id'  => $sectionMap[$sectionData['slug']],
                    'enabled'     => 1,
                    'sort_order'  => $sectionData['sort_order'] ?? 0,
                    'is_required' => $sectionData['is_required'] ?? 0,
                ];
            }
            $this->typeService->updateTypeSections($targetTypeId, $typeSections);

            // 3. Ensure all field definitions exist and update type-fields per section
            foreach ($config['sections'] as $sectionData) {
                $sectionId = $sectionMap[$sectionData['slug']];
                $typeFields = [];

                foreach ($sectionData['fields'] ?? [] as $fieldData) {
                    $fieldDef = $this->typeService->getFieldDefinition($fieldData['field_key']);

                    if (!$fieldDef) {
                        // Create new custom field definition
                        $fieldDefId = $this->typeService->createFieldDefinition([
                            'field_key'         => $fieldData['field_key'],
                            'label'             => $fieldData['label'],
                            'field_type'        => $fieldData['field_type'],
                            'options_json'      => $fieldData['options_json'] ?? null,
                            'widget'            => $fieldData['widget'] ?? null,
                            'is_legacy_column'  => $fieldData['is_legacy_column'] ?? 0,
                            'legacy_column_name' => $fieldData['is_legacy_column'] ? $fieldData['field_key'] : null,
                            'is_core'           => $fieldData['is_core'] ?? 0,
                            'input_suffix'      => $fieldData['input_suffix'] ?? null,
                        ]);
                    } else {
                        $fieldDefId = (int)$fieldDef['id'];
                    }

                    $typeFields[] = [
                        'field_definition_id' => $fieldDefId,
                        'enabled'             => 1,
                        'is_required'         => $fieldData['is_required'] ?? 0,
                        'sort_order'          => $fieldData['sort_order'] ?? 0,
                        'column_width'        => $fieldData['column_width'] ?? 'full',
                        'group_key'           => $fieldData['group_key'] ?? null,
                        'group_label'         => $fieldData['group_label'] ?? null,
                    ];
                }

                $this->typeService->updateTypeFields($targetTypeId, $sectionId, $typeFields);
            }

            // 4. Replace validation rules
            $existingRules = $this->typeService->getValidationRules($targetTypeId);
            foreach ($existingRules as $r) {
                $this->typeService->deleteValidationRule($r['id']);
            }

            foreach ($config['validation_rules'] ?? [] as $ruleData) {
                $this->typeService->createValidationRule([
                    'protocol_type_id' => $targetTypeId,
                    'name'             => $ruleData['name'],
                    'rule_json'        => $ruleData['rule_json'],
                    'error_message'    => $ruleData['error_message'],
                    'severity'         => $ruleData['severity'] ?? 'error',
                    'sort_order'       => $ruleData['sort_order'] ?? 0,
                ]);
            }

            $this->pdo->commit();
            ProtocolTypeService::clearCache();
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            error_log("Preset apply failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Import a preset from JSON string.
     */
    public function importFromJson(string $json, string $name, ?string $description = null, ?int $createdBy = null): ?int
    {
        $data = json_decode($json, true);
        if (!$data || !isset($data['sections'])) {
            return null;
        }

        return $this->save($name, $description ?? '', $data, $createdBy);
    }

    /**
     * Delete a preset (builtin presets cannot be deleted).
     */
    public function delete(int $id): bool
    {
        return $this->pdo->prepare("DELETE FROM intra_edivi_presets WHERE id = :id AND is_builtin = 0")
            ->execute(['id' => $id]);
    }
}
