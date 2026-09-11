<?php

namespace App\Documents;

use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateField;
use Illuminate\Database\Capsule\Manager as Capsule;

class DocumentTemplateManager
{
    /**
     * Zentrale Typ-Map fuer Legacy-Dokumenttypen (0-13).
     * Typ 99 = Template-basiertes Dokument (Name kommt aus template_name).
     */
    public static function getDocumentTypeLabel(int $type, ?string $templateName = null): string
    {
        if ($type === 99 && !empty($templateName)) {
            return $templateName;
        }

        $types = [
            0 => 'Ernennungsurkunde', 1 => 'Beförderungsurkunde', 2 => 'Entlassungsurkunde',
            3 => 'Ausbildungsvertrag', 5 => 'Ausbildungszertifikat', 6 => 'Lehrgangszertifikat',
            7 => 'Lehrgangszertifikat (Fachdienste)', 10 => 'Schriftliche Abmahnung',
            11 => 'Vorläufige Dienstenthebung', 12 => 'Dienstentfernung',
            13 => 'Außerordentliche Kündigung', 99 => 'Eigenes Dokument',
        ];
        return $types[$type] ?? 'Unbekannt';
    }

    /**
     * Erstellt ein neues Dokumenten-Template
     */
    public function createTemplate(array $data): int
    {
        // category_id aus der neuen Kategorien-Tabelle, category als ENUM-Fallback
        $categoryId = $data['category_id'] ?? null;
        $category = $data['category'] ?? $this->resolveCategoryEnum($categoryId);

        $attributes = [
            'name' => $data['name'],
            'category' => $category,
            'category_id' => $categoryId,
            'description' => $data['description'] ?? null,
            'template_file' => $data['template_file'] ?? null,
            'created_by' => $_SESSION['user_id'] ?? null,
        ];

        if ($this->hasEditorTypeColumn()) {
            $attributes['editor_type'] = $data['editor_type'] ?? 'visual';
        }

        $template = DocumentTemplate::create($attributes);

        return (int) $template->id;
    }

    /**
     * Prüft ob die editor_type-Spalte existiert (Abwärtskompatibilität)
     */
    private function hasEditorTypeColumn(): bool
    {
        static $hasColumn = null;
        if ($hasColumn !== null) return $hasColumn;

        try {
            $hasColumn = Capsule::schema()->hasColumn('intra_dokument_templates', 'editor_type');
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

        $name = DocumentCategory::where('id', $categoryId)->value('name');

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
        $field = DocumentTemplateField::create([
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
                : null,
        ]);

        return (int) $field->id;
    }

    /**
     * Lädt ein Template mit allen Feldern
     */
    public function getTemplate(int $templateId): ?array
    {
        $templateModel = DocumentTemplate::find($templateId);

        if (!$templateModel) {
            return null;
        }

        $template = $templateModel->getAttributes();

        // Lade zugehörige Felder
        $template['fields'] = DocumentTemplateField::where('template_id', $templateId)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (DocumentTemplateField $field) => $field->getAttributes())
            ->all();

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
        $query = Capsule::table('intra_dokument_templates as t')
            ->leftJoin('intra_dokument_kategorien as dk', 't.category_id', '=', 'dk.id')
            ->select('t.*', 'dk.name as category_name', 'dk.color as category_color', 'dk.icon as category_icon');

        if ($categoryId) {
            $query->where('t.category_id', $categoryId);
        } elseif ($category) {
            $query->where('t.category', $category);
        }

        return $query
            ->orderBy('dk.sort_order')
            ->orderBy('t.name')
            ->get()
            ->map(static function ($row): array {
                $template = (array) $row;
                // Die Aufrufer setzen category_color direkt als Klasse ein.
                $template['category_color'] = DocumentCategory::chipClass($template['category_color']);

                return $template;
            })
            ->all();
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
            $docId = DocumentIdGenerator::generate();
        }

        // Insert bewusst über den Query-Builder: das PersonnelDocument-Model
        // castet `docid` als Integer, hier ist die docid aber alphanumerisch.
        return (int) Capsule::table('intra_mitarbeiter_dokumente')->insertGetId([
            'docid' => $docId,
            'profileid' => $profileId,
            'template_id' => $templateId,
            'type' => 99,
            'custom_data' => json_encode($formData),
            'ausstellerid' => $_SESSION['discordtag'] ?? null,
            'ausstellungsdatum' => $formData['ausstellungsdatum'] ?? date('Y-m-d'),
            'erhalter' => $formData['erhalter'] ?? null,
            'erhalter_gebdat' => $formData['erhalter_gebdat'] ?? null,
            'anrede' => $formData['anrede'] ?? null,
        ]);
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
                        $validIds = Capsule::table('intra_mitarbeiter_dienstgrade')
                            ->where('archive', 0)
                            ->pluck('id')
                            ->all();
                        if (!in_array($value, $validIds)) {
                            throw new \Exception("Ungültiger Wert für '{$field['field_label']}'");
                        }
                        break;

                    case 'db_rdq':
                        $validIds = Capsule::table('intra_mitarbeiter_rdquali')
                            ->where('none', 0)
                            ->pluck('id')
                            ->all();
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
            return '<div class="ignis-alert ignis-alert--danger">Template nicht gefunden</div>';
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
        $html .= "<label for='{$name}' class='ignis-field__label'>{$label}";

        if ($field['is_required']) {
            $html .= ' <span class="text-[#d46b6b]">*</span>';
        }

        $html .= '</label>';

        switch ($field['field_type']) {
            case 'text':
                $html .= "<input type='text' class='ignis-input' id='{$name}' name='{$name}' {$required}>";
                break;

            case 'textarea':
                $html .= "<textarea class='ignis-input' id='{$name}' name='{$name}' rows='4' {$required}></textarea>";
                break;

            case 'date':
                $html .= "<input type='date' class='ignis-input' id='{$name}' name='{$name}' {$required}>";
                break;

            case 'number':
                $html .= "<input type='number' class='ignis-input' id='{$name}' name='{$name}' {$required}>";
                break;

            case 'select':
                $html .= "<select class='ignis-input' id='{$name}' name='{$name}' {$required}>";
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
                $html .= "<textarea class='ignis-input ckeditor' id='{$name}' name='{$name}' {$required}></textarea>";
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

        $attributes = [
            'name' => $data['name'],
            'category' => $category,
            'category_id' => $categoryId,
            'description' => $data['description'] ?? null,
            'template_file' => $data['template_file'] ?? null,
            'updated_at' => Capsule::raw('CURRENT_TIMESTAMP'),
        ];

        if ($this->hasEditorTypeColumn()) {
            $attributes['editor_type'] = $data['editor_type'] ?? 'visual';
        }

        DocumentTemplate::where('id', $templateId)->update($attributes);

        return true;
    }

    /**
     * Löscht ein Template (nur wenn nicht System-Template)
     */
    public function deleteTemplate(int $templateId): bool
    {
        DocumentTemplate::where('id', $templateId)
            ->where('is_system', 0)
            ->delete();

        return true;
    }

    /**
     * Dupliziert ein Template mit allen Feldern und dem aktiven Layout.
     *
     * @return int ID des neuen Templates
     */
    public function duplicateTemplate(int $sourceTemplateId): int
    {
        $source = $this->getTemplate($sourceTemplateId);
        if (!$source) {
            throw new \Exception('Quell-Template nicht gefunden');
        }

        // 1. Template-Metadaten kopieren
        $newId = $this->createTemplate([
            'name' => 'Kopie von ' . $source['name'],
            'category' => $source['category'] ?? 'sonstiges',
            'category_id' => $source['category_id'] ?? null,
            'description' => $source['description'] ?? null,
            'template_file' => $source['template_file'] ?? null,
            'editor_type' => $source['editor_type'] ?? 'visual',
        ]);

        // 2. Felder kopieren
        $fields = $source['fields'] ?? [];
        foreach ($fields as $field) {
            $this->addField($newId, [
                'field_name' => $field['field_name'],
                'field_label' => $field['field_label'],
                'field_type' => $field['field_type'],
                'field_options' => $field['field_options'],
                'is_required' => $field['is_required'],
                'gender_specific' => $field['gender_specific'],
                'sort_order' => $field['sort_order'],
                'validation_rules' => $field['validation_rules'],
            ]);
        }

        // 3. Aktives Layout kopieren (falls vorhanden)
        $layoutManager = new TemplateLayoutManager();
        $sourceLayout = $layoutManager->getLayout($sourceTemplateId);
        if ($sourceLayout) {
            $layoutManager->saveLayout(
                $newId,
                $sourceLayout['canvas_json'],
                $sourceLayout['page_width_mm'] ?? null,
                $sourceLayout['page_height_mm'] ?? null
            );
        }

        // 4. Config kopieren (falls vorhanden)
        if (!empty($source['config'])) {
            DocumentTemplate::where('id', $newId)->update([
                'config' => is_string($source['config']) ? $source['config'] : json_encode($source['config']),
            ]);
        }

        return $newId;
    }
}
