<?php

namespace App\Enotf;

use PDO;

class ValidationEngine
{
    private PDO $pdo;
    private ProtocolTypeService $typeService;

    public function __construct(PDO $pdo, ?ProtocolTypeService $typeService = null)
    {
        $this->pdo = $pdo;
        $this->typeService = $typeService ?? new ProtocolTypeService($pdo);
    }

    /**
     * Run all validation checks for a protocol.
     *
     * @return array{errors: array, warnings: array} Lists of validation messages
     */
    public function validate(int $protocolTypeId, array $daten): array
    {
        $errors = [];
        $warnings = [];

        // 1. Check required fields from type_fields
        $requiredErrors = $this->checkRequiredFields($protocolTypeId, $daten);
        $errors = array_merge($errors, $requiredErrors);

        // 2. Evaluate validation rules (overrides + additions + custom rules)
        $ruleResults = $this->evaluateRules($protocolTypeId, $daten);

        // Apply rule results: override rules can remove errors, addition rules can add them
        foreach ($ruleResults as $result) {
            if ($result['action'] === 'remove_errors') {
                $errors = array_filter($errors, function ($e) use ($result) {
                    return !in_array($e['field_key'], $result['fields']);
                });
            } elseif ($result['action'] === 'add_errors') {
                foreach ($result['errors'] as $err) {
                    if ($err['severity'] === 'error') {
                        $errors[] = $err;
                    } else {
                        $warnings[] = $err;
                    }
                }
            }
        }

        return ['errors' => array_values($errors), 'warnings' => $warnings];
    }

    /**
     * Check all required fields and return errors for empty ones.
     */
    private function checkRequiredFields(int $typeId, array $daten): array
    {
        $errors = [];

        $fields = $this->typeService->getAllFieldsForType($typeId);
        foreach ($fields as $field) {
            if (!$field['is_required']) {
                continue;
            }

            $key = $field['field_key'];
            $isEmpty = $this->isFieldEmpty($key, $daten);

            if ($isEmpty) {
                $sectionLabel = $this->getSectionLabel($field['section_id']);
                $errors[] = [
                    'field_key'  => $key,
                    'section_id' => (int)$field['section_id'],
                    'message'    => "[$sectionLabel] " . $field['label'] . ' ist nicht gesetzt.',
                    'severity'   => 'error',
                ];
            }
        }

        return $errors;
    }

    /**
     * Evaluate all validation rules for a protocol type.
     *
     * @return array List of action results
     */
    private function evaluateRules(int $typeId, array $daten): array
    {
        $rules = $this->typeService->getValidationRules($typeId);
        $results = [];

        foreach ($rules as $rule) {
            $ruleData = json_decode($rule['rule_json'], true);
            if (!$ruleData) {
                continue;
            }

            $type = $ruleData['type'] ?? 'custom';
            $condition = $ruleData['condition'] ?? null;
            $action = $ruleData['action'] ?? null;

            if (!$condition || !$action) {
                continue;
            }

            $conditionMet = $this->evaluateCondition($condition, $daten);

            if ($type === 'override' && $conditionMet) {
                // Override: make target fields optional → remove their errors
                $targetFields = $action['target_fields'] ?? [];
                $results[] = [
                    'action' => 'remove_errors',
                    'fields' => $targetFields,
                ];
            } elseif ($type === 'addition' && $conditionMet) {
                // Addition: make target fields required → add errors if empty
                $targetFields = $action['target_fields'] ?? [];
                $addErrors = [];

                foreach ($targetFields as $fieldKey) {
                    if ($this->isFieldEmpty($fieldKey, $daten)) {
                        $addErrors[] = [
                            'field_key'  => $fieldKey,
                            'section_id' => null,
                            'message'    => $rule['error_message'],
                            'severity'   => $rule['severity'],
                        ];
                    }
                }

                if (!empty($addErrors)) {
                    $results[] = [
                        'action' => 'add_errors',
                        'errors' => $addErrors,
                    ];
                }
            } elseif ($type === 'custom' && $conditionMet) {
                // Custom rule: condition met means validation failed
                $targetField = $action['target_field'] ?? null;
                $results[] = [
                    'action' => 'add_errors',
                    'errors' => [[
                        'field_key'  => $targetField,
                        'section_id' => null,
                        'message'    => $rule['error_message'],
                        'severity'   => $rule['severity'],
                    ]],
                ];
            }
        }

        return $results;
    }

    /**
     * Recursively evaluate a condition tree.
     */
    public function evaluateCondition(array $condition, array $daten): bool
    {
        $type = $condition['type'] ?? 'condition';

        if ($type === 'group') {
            return $this->evaluateGroup($condition, $daten);
        }

        // Single condition
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $expected = $condition['value'] ?? null;
        $actual = $daten[$field] ?? null;

        return match ($operator) {
            'equals'       => (string)$actual === (string)$expected,
            'not_equals'   => (string)$actual !== (string)$expected,
            'greater_than' => is_numeric($actual) && is_numeric($expected) && (float)$actual > (float)$expected,
            'less_than'    => is_numeric($actual) && is_numeric($expected) && (float)$actual < (float)$expected,
            'is_empty'     => $this->isFieldEmpty($field, $daten),
            'is_not_empty' => !$this->isFieldEmpty($field, $daten),
            'in_list'      => is_array($expected) && in_array((string)$actual, array_map('strval', $expected)),
            'not_in_list'  => is_array($expected) && !in_array((string)$actual, array_map('strval', $expected)),
            default        => false,
        };
    }

    /**
     * Evaluate a group of conditions with AND/OR logic.
     */
    private function evaluateGroup(array $group, array $daten): bool
    {
        $operator = strtoupper($group['operator'] ?? 'AND');
        $conditions = $group['conditions'] ?? [];

        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            $result = $this->evaluateCondition($condition, $daten);

            if ($operator === 'OR' && $result) {
                return true;
            }
            if ($operator === 'AND' && !$result) {
                return false;
            }
        }

        return $operator === 'AND';
    }

    /**
     * Check if a field is considered empty.
     */
    private function isFieldEmpty(string $fieldKey, array $daten): bool
    {
        $value = $daten[$fieldKey] ?? null;

        if ($value === null) {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        // JSON arrays: '[]' or '""' are empty
        if (is_string($value) && (
            $value === '[]' ||
            $value === '""' ||
            $value === 'null'
        )) {
            return true;
        }

        return false;
    }

    /**
     * Get section display label for error messages.
     */
    private function getSectionLabel(int $sectionId): string
    {
        static $labels = null;
        if ($labels === null) {
            $labels = [];
            $sections = $this->typeService->getAllSections();
            foreach ($sections as $s) {
                $labels[$s['id']] = $s['name'];
            }
        }
        return $labels[$sectionId] ?? "Sektion $sectionId";
    }

    /**
     * Generate conditions config in the format expected by the existing JS (field_checks.php / notify.php).
     * This provides backward compatibility with the existing client-side validation.
     *
     * @return array{base: array, overrides: array, additions: array}
     */
    public function getConditionsForJs(int $typeId): array
    {
        $base = [];
        $overrides = [];
        $additions = [];

        // Base: all required fields
        $fields = $this->typeService->getAllFieldsForType($typeId);
        foreach ($fields as $field) {
            if (!$field['is_required']) {
                continue;
            }

            $base[$field['field_key']] = [
                'html'    => [$field['field_key']],
                'db'      => [$field['field_key']],
                'section' => (int)$field['section_id'],
            ];
        }

        // Rules: convert to overrides/additions format
        $rules = $this->typeService->getValidationRules($typeId);
        foreach ($rules as $rule) {
            $ruleData = json_decode($rule['rule_json'], true);
            if (!$ruleData) {
                continue;
            }

            $type = $ruleData['type'] ?? 'custom';
            $condition = $ruleData['condition'] ?? null;
            $action = $ruleData['action'] ?? null;

            if (!$condition || !$action) {
                continue;
            }

            if ($type === 'override') {
                // Extract transportziel value from condition
                $tzValue = $this->extractTransportzielValue($condition);
                if ($tzValue !== null) {
                    $overrides[$tzValue] = $action['target_fields'] ?? [];
                }
            } elseif ($type === 'addition') {
                $tzValues = $this->extractTransportzielValues($condition);
                $targetFields = $action['target_fields'] ?? [];

                foreach ($tzValues as $tzValue) {
                    if (!isset($additions[$tzValue])) {
                        $additions[$tzValue] = [];
                    }
                    foreach ($targetFields as $fieldKey) {
                        $additions[$tzValue][$fieldKey] = [
                            'html'    => [$fieldKey],
                            'db'      => [$fieldKey],
                            'section' => 1, // Default to Rettdaten for transport fields
                        ];
                    }
                }
            }
        }

        return [
            'base'      => $base,
            'overrides' => $overrides,
            'additions' => $additions,
        ];
    }

    private function extractTransportzielValue(array $condition): ?string
    {
        if (($condition['field'] ?? '') === 'transportziel' && ($condition['operator'] ?? '') === 'equals') {
            return (string)$condition['value'];
        }
        return null;
    }

    private function extractTransportzielValues(array $condition): array
    {
        if (($condition['field'] ?? '') === 'transportziel') {
            if (($condition['operator'] ?? '') === 'in_list' && is_array($condition['value'] ?? null)) {
                return array_map('strval', $condition['value']);
            }
            if (($condition['operator'] ?? '') === 'equals') {
                return [(string)$condition['value']];
            }
        }
        return [];
    }
}
