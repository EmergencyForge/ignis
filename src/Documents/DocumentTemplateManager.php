<?php

namespace App\Documents;

use PDO;

class DocumentTemplateManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Erstellt ein neues Dokumenten-Template
     */
    public function createTemplate(array $data): int
    {
        // category_id aus der neuen Kategorien-Tabelle, category als ENUM-Fallback
        $categoryId = $data['category_id'] ?? null;
        $category = $data['category'] ?? $this->resolveCategoryEnum($categoryId);

        $params = [
            'name' => $data['name'],
            'category' => $category,
            'category_id' => $categoryId,
            'description' => $data['description'] ?? null,
            'template_file' => $data['template_file'] ?? null,
            'created_by' => $_SESSION['user_id'] ?? null
        ];

        if ($this->hasEditorTypeColumn()) {
            $sql = "INSERT INTO intra_dokument_templates
                (name, category, category_id, description, template_file, editor_type, created_by)
                VALUES (:name, :category, :category_id, :description, :template_file, :editor_type, :created_by)";
            $params['editor_type'] = $data['editor_type'] ?? 'visual';
        } else {
            $sql = "INSERT INTO intra_dokument_templates
                (name, category, category_id, description, template_file, created_by)
                VALUES (:name, :category, :category_id, :description, :template_file, :created_by)";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Prüft ob die editor_type-Spalte existiert (Abwärtskompatibilität)
     */
    private function hasEditorTypeColumn(): bool
    {
        static $hasColumn = null;
        if ($hasColumn !== null) return $hasColumn;

        try {
            $stmt = $this->pdo->prepare("
                SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'intra_dokument_templates'
                AND COLUMN_NAME = 'editor_type'
            ");
            $stmt->execute();
            $hasColumn = (bool) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            $hasColumn = false;
        }
        return $hasColumn;
    }

    private function resolveCategoryEnum(?int $categoryId): string
    {
        if (!$categoryId) {
            return 'sonstiges';
        }

        $stmt = $this->pdo->prepare("SELECT name FROM intra_dokument_kategorien WHERE id = :id");
        $stmt->execute(['id' => $categoryId]);
        $name = $stmt->fetchColumn();

        // Mappe auf bestehende ENUM-Werte für Abwärtskompatibilität
        $enumMap = [
            'Urkunde' => 'urkunde',
            'Zertifikat' => 'zertifikat',
            'Schreiben' => 'schreiben',
        ];

        return $enumMap[$name] ?? 'sonstiges';
    }

    /**
     * Fügt ein Formularfeld zu einem Template hinzu
     */
    public function addField(int $templateId, array $fieldData): int
    {
        $stmt = $this->pdo->prepare("
        INSERT INTO intra_dokument_template_fields 
        (template_id, field_name, field_label, field_type, field_options, 
         is_required, gender_specific, sort_order, validation_rules)
        VALUES (:template_id, :field_name, :field_label, :field_type, 
                :field_options, :is_required, :gender_specific, :sort_order, :validation_rules)
    ");

        $stmt->execute([
            'template_id' => $templateId,
            'field_name' => $fieldData['field_name'],
            'field_label' => $fieldData['field_label'],
            'field_type' => $fieldData['field_type'],
            'field_options' => isset($fieldData['field_options'])
                ? json_encode($fieldData['field_options'])
                : null,
            'is_required' => !empty($fieldData['is_required']) ? 1 : 0,
            'gender_specific' => !empty($fieldData['gender_specific']) ? 1 : 0,
            'sort_order' => $fieldData['sort_order'] ?? 0,
            'validation_rules' => isset($fieldData['validation_rules'])
                ? json_encode($fieldData['validation_rules'])
                : null
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Lädt ein Template mit allen Feldern
     */
    public function getTemplate(int $templateId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM intra_dokument_templates WHERE id = :id
        ");
        $stmt->execute(['id' => $templateId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template) {
            return null;
        }

        // Lade zugehörige Felder
        $stmt = $this->pdo->prepare("
            SELECT * FROM intra_dokument_template_fields 
            WHERE template_id = :template_id 
            ORDER BY sort_order ASC
        ");
        $stmt->execute(['template_id' => $templateId]);
        $template['fields'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Dekodiere JSON-Felder
        foreach ($template['fields'] as &$field) {
            if ($field['field_options']) {
                $field['field_options'] = json_decode($field['field_options'] ?? '[]', true);
            }
            if ($field['validation_rules']) {
                $field['validation_rules'] = json_decode($field['validation_rules'] ?? '[]', true);
            }
        }

        return $template;
    }

    /**
     * Listet alle verfügbaren Templates auf
     */
    public function listTemplates(?string $category = null, ?int $categoryId = null): array
    {
        $sql = "SELECT t.*, dk.name as category_name, dk.color as category_color, dk.icon as category_icon
                FROM intra_dokument_templates t
                LEFT JOIN intra_dokument_kategorien dk ON t.category_id = dk.id";
        $params = [];
        $where = [];

        if ($categoryId) {
            $where[] = "t.category_id = :category_id";
            $params['category_id'] = $categoryId;
        } elseif ($category) {
            $where[] = "t.category = :category";
            $params['category'] = $category;
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY dk.sort_order ASC, t.name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createDocument(int $templateId, int $profileId, array $formData, ?string $docId = null): int
    {
        $template = $this->getTemplate($templateId);

        if (!$template) {
            throw new \Exception("Template nicht gefunden");
        }

        // Validiere Formulardaten
        $this->validateFormData($template, $formData);

        // Generiere docid falls nicht übergeben
        if ($docId === null) {
            $docId = DocumentIdGenerator::generate($this->pdo);
        }

        $stmt = $this->pdo->prepare("
        INSERT INTO intra_mitarbeiter_dokumente 
        (docid, profileid, template_id, type, custom_data, ausstellerid, 
         ausstellungsdatum, erhalter, erhalter_gebdat, anrede)
        VALUES (:docid, :profileid, :template_id, 99, :custom_data, 
                :ausstellerid, :ausstellungsdatum, :erhalter, 
                :erhalter_gebdat, :anrede)
    ");

        $stmt->execute([
            'docid' => $docId,
            'profileid' => $profileId,
            'template_id' => $templateId,
            'custom_data' => json_encode($formData),
            'ausstellerid' => $_SESSION['discordtag'] ?? null,
            'ausstellungsdatum' => $formData['ausstellungsdatum'] ?? date('Y-m-d'),
            'erhalter' => $formData['erhalter'] ?? null,
            'erhalter_gebdat' => $formData['erhalter_gebdat'] ?? null,
            'anrede' => $formData['anrede'] ?? null
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Validiert Formulardaten gegen Template-Definition
     */
    private function validateFormData(array $template, array $formData): void
    {
        foreach ($template['fields'] as $field) {
            $fieldName = $field['field_name'];
            $value = $formData[$fieldName] ?? null;

            // Pflichtfeld-Prüfung
            if ($field['is_required'] && ($value === null || $value === '')) {
                throw new \Exception("Feld '{$field['field_label']}' ist erforderlich");
            }

            // Typ-Validierung NUR wenn Wert vorhanden ist
            if ($value !== null && $value !== '') {
                switch ($field['field_type']) {
                    case 'date':
                        if (!strtotime($value)) {
                            throw new \Exception("Ungültiges Datum für '{$field['field_label']}'");
                        }
                        break;

                    case 'number':
                        if (!is_numeric($value)) {
                            throw new \Exception("'{$field['field_label']}' muss eine Zahl sein");
                        }
                        break;

                    case 'db_dg':
                        $stmt = $this->pdo->query("SELECT id FROM intra_mitarbeiter_dienstgrade WHERE archive = 0");
                        $validIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        if (!in_array($value, $validIds)) {
                            throw new \Exception("Ungültiger Wert für '{$field['field_label']}'");
                        }
                        break;

                    case 'db_rdq':
                        $stmt = $this->pdo->query("SELECT id FROM intra_mitarbeiter_rdquali WHERE none = 0");
                        $validIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        if (!in_array($value, $validIds)) {
                            throw new \Exception("Ungültiger Wert für '{$field['field_label']}'");
                        }
                        break;

                    case 'select':
                        if (isset($field['field_options'])) {
                            $options = array_column($field['field_options'], 'value');
                            if (!in_array($value, $options)) {
                                throw new \Exception("Ungültiger Wert für '{$field['field_label']}'");
                            }
                        }
                        break;
                }
            }

            // Custom Validierungsregeln
            if (isset($field['validation_rules']) && $value !== null && $value !== '') {
                $this->applyValidationRules($field, $value);
            }
        }
    }

    /**
     * Wendet Custom-Validierungsregeln an
     */
    private function applyValidationRules(array $field, $value): void
    {
        $rules = $field['validation_rules'];

        if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
            throw new \Exception("{$field['field_label']} muss mindestens {$rules['min_length']} Zeichen lang sein");
        }

        if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
            throw new \Exception("{$field['field_label']} darf maximal {$rules['max_length']} Zeichen lang sein");
        }

        if (isset($rules['pattern']) && !preg_match($rules['pattern'], $value)) {
            throw new \Exception("{$field['field_label']} hat ein ungültiges Format");
        }
    }

    /**
     * Rendert das Formular für ein Template
     */
    public function renderForm(int $templateId): string
    {
        $template = $this->getTemplate($templateId);

        if (!$template) {
            return '<div class="alert alert-danger">Template nicht gefunden</div>';
        }

        $html = '<input type="hidden" name="template_id" value="' . $templateId . '">';

        foreach ($template['fields'] as $field) {
            $html .= $this->renderField($field);
        }

        return $html;
    }

    /**
     * Rendert ein einzelnes Formularfeld
     */
    private function renderField(array $field): string
    {
        $required = $field['is_required'] ? 'required' : '';
        $label = htmlspecialchars($field['field_label']);
        $name = htmlspecialchars($field['field_name']);

        $html = '<div class="mb-3">';
        $html .= "<label for='{$name}' class='form-label'>{$label}";

        if ($field['is_required']) {
            $html .= ' <span class="text-danger">*</span>';
        }

        $html .= '</label>';

        switch ($field['field_type']) {
            case 'text':
                $html .= "<input type='text' class='form-control' id='{$name}' name='{$name}' {$required}>";
                break;

            case 'textarea':
                $html .= "<textarea class='form-control' id='{$name}' name='{$name}' rows='4' {$required}></textarea>";
                break;

            case 'date':
                $html .= "<input type='date' class='form-control' id='{$name}' name='{$name}' {$required}>";
                break;

            case 'number':
                $html .= "<input type='number' class='form-control' id='{$name}' name='{$name}' {$required}>";
                break;

            case 'select':
                $html .= "<select class='form-select' id='{$name}' name='{$name}' {$required}>";
                $html .= "<option value='' disabled selected>Bitte wählen</option>";

                if (isset($field['field_options'])) {
                    foreach ($field['field_options'] as $option) {
                        $value = htmlspecialchars($option['value']);
                        $label = htmlspecialchars($option['label']);
                        $html .= "<option value='{$value}'>{$label}</option>";
                    }
                }

                $html .= '</select>';
                break;

            case 'richtext':
                $html .= "<textarea class='form-control ckeditor' id='{$name}' name='{$name}' {$required}></textarea>";
                break;
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Aktualisiert ein Template
     */
    public function updateTemplate(int $templateId, array $data): bool
    {
        $categoryId = $data['category_id'] ?? null;
        $category = $data['category'] ?? $this->resolveCategoryEnum($categoryId);

        $params = [
            'id' => $templateId,
            'name' => $data['name'],
            'category' => $category,
            'category_id' => $categoryId,
            'description' => $data['description'] ?? null,
            'template_file' => $data['template_file'] ?? null,
        ];

        if ($this->hasEditorTypeColumn()) {
            $sql = "UPDATE intra_dokument_templates
                SET name = :name, category = :category, category_id = :category_id,
                    description = :description, template_file = :template_file,
                    editor_type = :editor_type, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
            $params['editor_type'] = $data['editor_type'] ?? 'visual';
        } else {
            $sql = "UPDATE intra_dokument_templates
                SET name = :name, category = :category, category_id = :category_id,
                    description = :description, template_file = :template_file,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Löscht ein Template (nur wenn nicht System-Template)
     */
    public function deleteTemplate(int $templateId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM intra_dokument_templates 
            WHERE id = :id AND is_system = 0
        ");

        return $stmt->execute(['id' => $templateId]);
    }
}
