<?php

namespace App\Enotf;

use PDO;

class ProtocolDataService
{
    private PDO $pdo;
    private ProtocolTypeService $typeService;

    public function __construct(PDO $pdo, ?ProtocolTypeService $typeService = null)
    {
        $this->pdo = $pdo;
        $this->typeService = $typeService ?? new ProtocolTypeService($pdo);
    }

    /**
     * Merged protocol data: legacy columns + custom field values.
     * Returns the same array structure as the current $daten pattern.
     */
    public function getFullProtocolData(string $enr): ?array
    {
        // 1. Legacy data from intra_edivi
        $stmt = $this->pdo->prepare("SELECT * FROM intra_edivi WHERE enr = :enr");
        $stmt->execute(['enr' => $enr]);
        $daten = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$daten) {
            return null;
        }

        // 2. Custom field values
        $customValues = $this->getCustomValues($daten['id']);
        foreach ($customValues as $key => $value) {
            $daten[$key] = $value;
        }

        return $daten;
    }

    /**
     * Get custom field values for a protocol.
     *
     * @return array<string, string> field_key => field_value
     */
    public function getCustomValues(int $protocolId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT field_key, field_value
            FROM intra_edivi_custom_values
            WHERE protocol_id = :id
        ");
        $stmt->execute(['id' => $protocolId]);

        $values = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $values[$row['field_key']] = $row['field_value'];
        }

        return $values;
    }

    /**
     * Save a single field value. Dispatches to legacy column or custom values table.
     */
    public function saveField(string $enr, string $fieldKey, ?string $value): bool
    {
        $fieldDef = $this->typeService->getFieldDefinition($fieldKey);
        if (!$fieldDef) {
            return false;
        }

        if ($fieldDef['is_legacy_column']) {
            return $this->saveLegacyField($enr, $fieldDef['legacy_column_name'], $value);
        }

        return $this->saveCustomField($enr, $fieldKey, $value);
    }

    /**
     * Save to a legacy column in intra_edivi.
     */
    private function saveLegacyField(string $enr, string $columnName, ?string $value): bool
    {
        // Whitelist-Validation: column must exist as legacy field
        $col = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);

        $sql = "UPDATE intra_edivi SET `$col` = :value, last_edit = NOW() WHERE enr = :enr AND (freigegeben = 0 OR freigegeben IS NULL)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['value' => $value, 'enr' => $enr]);
    }

    /**
     * Save to the EAV custom values table.
     */
    private function saveCustomField(string $enr, string $fieldKey, ?string $value): bool
    {
        // Get protocol ID
        $stmt = $this->pdo->prepare("SELECT id FROM intra_edivi WHERE enr = :enr");
        $stmt->execute(['enr' => $enr]);
        $protocol = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$protocol) {
            return false;
        }

        // Check protocol is not released
        $checkStmt = $this->pdo->prepare("SELECT freigegeben FROM intra_edivi WHERE id = :id");
        $checkStmt->execute(['id' => $protocol['id']]);
        $status = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if ($status && $status['freigegeben'] == 1) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO intra_edivi_custom_values (protocol_id, enr, field_key, field_value)
            VALUES (:protocol_id, :enr, :field_key, :value)
            ON DUPLICATE KEY UPDATE field_value = :value2
        ");

        return $stmt->execute([
            'protocol_id' => $protocol['id'],
            'enr'         => $enr,
            'field_key'   => $fieldKey,
            'value'       => $value,
            'value2'      => $value,
        ]);
    }

    /**
     * Get dynamic whitelist of allowed fields for a protocol type.
     */
    public function getAllowedFields(int $protocolTypeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT fd.field_key
            FROM intra_edivi_type_fields tf
            JOIN intra_edivi_field_definitions fd ON fd.id = tf.field_definition_id
            WHERE tf.protocol_type_id = :type_id AND tf.enabled = 1
        ");
        $stmt->execute(['type_id' => $protocolTypeId]);

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'field_key');
    }

    /**
     * Get required fields for a protocol type.
     *
     * @return array<string, array> field_key => field config
     */
    public function getRequiredFields(int $protocolTypeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT fd.field_key, fd.label, tf.section_id
            FROM intra_edivi_type_fields tf
            JOIN intra_edivi_field_definitions fd ON fd.id = tf.field_definition_id
            WHERE tf.protocol_type_id = :type_id
              AND tf.enabled = 1
              AND tf.is_required = 1
        ");
        $stmt->execute(['type_id' => $protocolTypeId]);

        $fields = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $fields[$row['field_key']] = [
                'label'      => $row['label'],
                'section_id' => (int)$row['section_id'],
            ];
        }

        return $fields;
    }
}
