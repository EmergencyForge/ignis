<?php

namespace App\Enotf;

use PDO;

class ProtocolTypeService
{
    private PDO $pdo;
    private static ?array $typeCache = null;
    private static ?array $sectionCache = null;
    private static ?array $fieldCache = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ──── Protocol Types ────

    public function getAllTypes(bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM intra_edivi_protocol_types";
        if ($activeOnly) {
            $sql .= " WHERE active = 1";
        }
        $sql .= " ORDER BY sort_order ASC";

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getType(int $id): ?array
    {
        if (self::$typeCache !== null && isset(self::$typeCache[$id])) {
            return self::$typeCache[$id];
        }

        $stmt = $this->pdo->prepare("SELECT * FROM intra_edivi_protocol_types WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $type = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($type) {
            self::$typeCache[$id] = $type;
        }

        return $type ?: null;
    }

    public function getTypeBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM intra_edivi_protocol_types WHERE slug = :slug");
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function createType(array $data): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO intra_edivi_protocol_types
            (slug, name, short_name, description, color, icon, is_builtin, active, sort_order, created_by)
            VALUES (:slug, :name, :short_name, :description, :color, :icon, 0, :active, :sort_order, :created_by)");

        $stmt->execute([
            'slug'        => $data['slug'],
            'name'        => $data['name'],
            'short_name'  => $data['short_name'],
            'description' => $data['description'] ?? null,
            'color'       => $data['color'] ?? '#dc3545',
            'icon'        => $data['icon'] ?? null,
            'active'      => $data['active'] ?? 1,
            'sort_order'  => $data['sort_order'] ?? 0,
            'created_by'  => $data['created_by'] ?? null,
        ]);

        self::$typeCache = null;
        return (int)$this->pdo->lastInsertId();
    }

    public function updateType(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['name', 'short_name', 'description', 'color', 'icon', 'active', 'sort_order'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[] = "$key = :$key";
                $params[$key] = $data[$key];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE intra_edivi_protocol_types SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        self::$typeCache = null;
        return $stmt->execute($params);
    }

    public function deleteType(int $id): bool
    {
        $type = $this->getType($id);
        if (!$type || $type['is_builtin']) {
            return false;
        }

        $stmt = $this->pdo->prepare("DELETE FROM intra_edivi_protocol_types WHERE id = :id AND is_builtin = 0");
        self::$typeCache = null;
        return $stmt->execute(['id' => $id]);
    }

    // ──── Sections ────

    public function getAllSections(): array
    {
        return $this->pdo->query("SELECT * FROM intra_edivi_sections ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSectionsForType(int $typeId): array
    {
        $cacheKey = "type_$typeId";
        if (self::$sectionCache !== null && isset(self::$sectionCache[$cacheKey])) {
            return self::$sectionCache[$cacheKey];
        }

        $stmt = $this->pdo->prepare("
            SELECT s.*, ts.enabled, ts.sort_order AS type_sort_order, ts.is_required AS section_required
            FROM intra_edivi_type_sections ts
            JOIN intra_edivi_sections s ON s.id = ts.section_id
            WHERE ts.protocol_type_id = :type_id AND ts.enabled = 1
            ORDER BY ts.sort_order ASC
        ");
        $stmt->execute(['type_id' => $typeId]);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

        self::$sectionCache[$cacheKey] = $sections;
        return $sections;
    }

    public function createSection(array $data): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO intra_edivi_sections
            (slug, name, icon, is_builtin, has_subsections, component_template)
            VALUES (:slug, :name, :icon, 0, :has_subsections, :component_template)");

        $stmt->execute([
            'slug'               => $data['slug'],
            'name'               => $data['name'],
            'icon'               => $data['icon'] ?? null,
            'has_subsections'    => $data['has_subsections'] ?? 0,
            'component_template' => $data['component_template'] ?? null,
        ]);

        self::$sectionCache = null;
        return (int)$this->pdo->lastInsertId();
    }

    public function updateTypeSections(int $typeId, array $sections): void
    {
        $this->pdo->prepare("DELETE FROM intra_edivi_type_sections WHERE protocol_type_id = :type_id")
            ->execute(['type_id' => $typeId]);

        $stmt = $this->pdo->prepare("INSERT INTO intra_edivi_type_sections
            (protocol_type_id, section_id, enabled, sort_order, is_required)
            VALUES (:type_id, :section_id, :enabled, :sort_order, :is_required)");

        foreach ($sections as $i => $section) {
            $stmt->execute([
                'type_id'     => $typeId,
                'section_id'  => $section['section_id'],
                'enabled'     => $section['enabled'] ?? 1,
                'sort_order'  => $section['sort_order'] ?? ($i + 1),
                'is_required' => $section['is_required'] ?? 0,
            ]);
        }

        self::$sectionCache = null;
    }

    // ──── Field Definitions ────

    public function getFieldDefinition(string $fieldKey): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM intra_edivi_field_definitions WHERE field_key = :key");
        $stmt->execute(['key' => $fieldKey]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getFieldDefinitionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM intra_edivi_field_definitions WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getAllFieldDefinitions(): array
    {
        return $this->pdo->query("SELECT * FROM intra_edivi_field_definitions ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFieldsForSection(int $typeId, int $sectionId): array
    {
        $cacheKey = "{$typeId}_{$sectionId}";
        if (self::$fieldCache !== null && isset(self::$fieldCache[$cacheKey])) {
            return self::$fieldCache[$cacheKey];
        }

        $stmt = $this->pdo->prepare("
            SELECT fd.*, tf.enabled, tf.is_required, tf.sort_order AS type_sort_order,
                   tf.column_width, tf.group_key, tf.group_label, tf.quickfill_group,
                   tf.override_options_json
            FROM intra_edivi_type_fields tf
            JOIN intra_edivi_field_definitions fd ON fd.id = tf.field_definition_id
            WHERE tf.protocol_type_id = :type_id
              AND tf.section_id = :section_id
              AND tf.enabled = 1
            ORDER BY tf.sort_order ASC
        ");
        $stmt->execute(['type_id' => $typeId, 'section_id' => $sectionId]);
        $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);

        self::$fieldCache[$cacheKey] = $fields;
        return $fields;
    }

    public function getAllFieldsForType(int $typeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT fd.*, tf.enabled, tf.is_required, tf.sort_order AS type_sort_order,
                   tf.section_id, tf.column_width, tf.group_key, tf.group_label
            FROM intra_edivi_type_fields tf
            JOIN intra_edivi_field_definitions fd ON fd.id = tf.field_definition_id
            WHERE tf.protocol_type_id = :type_id AND tf.enabled = 1
            ORDER BY tf.section_id ASC, tf.sort_order ASC
        ");
        $stmt->execute(['type_id' => $typeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createFieldDefinition(array $data): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO intra_edivi_field_definitions
            (field_key, label, field_type, options_json, widget, is_legacy_column, legacy_column_name, is_core, default_value, placeholder, hint_text, input_suffix, min_value, max_value)
            VALUES (:field_key, :label, :field_type, :options_json, :widget, :is_legacy, :legacy_col, :is_core, :default_value, :placeholder, :hint_text, :suffix, :min_value, :max_value)");

        $stmt->execute([
            'field_key'     => $data['field_key'],
            'label'         => $data['label'],
            'field_type'    => $data['field_type'],
            'options_json'  => $data['options_json'] ?? null,
            'widget'        => $data['widget'] ?? null,
            'is_legacy'     => $data['is_legacy_column'] ?? 0,
            'legacy_col'    => $data['legacy_column_name'] ?? null,
            'is_core'       => $data['is_core'] ?? 0,
            'default_value' => $data['default_value'] ?? null,
            'placeholder'   => $data['placeholder'] ?? null,
            'hint_text'     => $data['hint_text'] ?? null,
            'suffix'        => $data['input_suffix'] ?? null,
            'min_value'     => $data['min_value'] ?? null,
            'max_value'     => $data['max_value'] ?? null,
        ]);

        self::$fieldCache = null;
        return (int)$this->pdo->lastInsertId();
    }

    public function updateTypeFields(int $typeId, int $sectionId, array $fields): void
    {
        $this->pdo->prepare("DELETE FROM intra_edivi_type_fields
            WHERE protocol_type_id = :type_id AND section_id = :section_id")
            ->execute(['type_id' => $typeId, 'section_id' => $sectionId]);

        $stmt = $this->pdo->prepare("INSERT INTO intra_edivi_type_fields
            (protocol_type_id, section_id, field_definition_id, enabled, is_required, sort_order, column_width, group_key, group_label, quickfill_group)
            VALUES (:type_id, :section_id, :field_def_id, :enabled, :required, :sort_order, :width, :group_key, :group_label, :quickfill)");

        foreach ($fields as $i => $field) {
            $stmt->execute([
                'type_id'      => $typeId,
                'section_id'   => $sectionId,
                'field_def_id' => $field['field_definition_id'],
                'enabled'      => $field['enabled'] ?? 1,
                'required'     => $field['is_required'] ?? 0,
                'sort_order'   => $field['sort_order'] ?? ($i + 1),
                'width'        => $field['column_width'] ?? 'full',
                'group_key'    => $field['group_key'] ?? null,
                'group_label'  => $field['group_label'] ?? null,
                'quickfill'    => $field['quickfill_group'] ?? null,
            ]);
        }

        self::$fieldCache = null;
    }

    // ──── Validation Rules ────

    public function getValidationRules(int $typeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM intra_edivi_validation_rules
            WHERE protocol_type_id = :type_id AND active = 1
            ORDER BY sort_order ASC
        ");
        $stmt->execute(['type_id' => $typeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createValidationRule(array $data): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO intra_edivi_validation_rules
            (protocol_type_id, name, rule_json, error_message, severity, active, sort_order)
            VALUES (:type_id, :name, :rule_json, :error_message, :severity, :active, :sort_order)");

        $stmt->execute([
            'type_id'       => $data['protocol_type_id'],
            'name'          => $data['name'],
            'rule_json'     => $data['rule_json'],
            'error_message' => $data['error_message'],
            'severity'      => $data['severity'] ?? 'error',
            'active'        => $data['active'] ?? 1,
            'sort_order'    => $data['sort_order'] ?? 0,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateValidationRule(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['name', 'rule_json', 'error_message', 'severity', 'active', 'sort_order'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[] = "$key = :$key";
                $params[$key] = $data[$key];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE intra_edivi_validation_rules SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->pdo->prepare($sql)->execute($params);
    }

    public function deleteValidationRule(int $id): bool
    {
        return $this->pdo->prepare("DELETE FROM intra_edivi_validation_rules WHERE id = :id")
            ->execute(['id' => $id]);
    }

    // ──── Cache Management ────

    public static function clearCache(): void
    {
        self::$typeCache = null;
        self::$sectionCache = null;
        self::$fieldCache = null;
    }
}
