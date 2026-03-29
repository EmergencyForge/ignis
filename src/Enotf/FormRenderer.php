<?php

namespace App\Enotf;

use PDO;

class FormRenderer
{
    private PDO $pdo;
    private ProtocolTypeService $typeService;
    private string $basePath;

    public function __construct(PDO $pdo, ?ProtocolTypeService $typeService = null, string $basePath = '')
    {
        $this->pdo = $pdo;
        $this->typeService = $typeService ?? new ProtocolTypeService($pdo);
        $this->basePath = $basePath;
    }

    /**
     * Render all fields for a section, grouped by group_key.
     */
    public function renderSection(int $typeId, int $sectionId, array $daten, bool $istFreigegeben = false): string
    {
        $fields = $this->typeService->getFieldsForSection($typeId, $sectionId);

        if (empty($fields)) {
            return '<div class="text-muted p-3">Keine Felder für diese Sektion konfiguriert.</div>';
        }

        // Group fields by group_key
        $groups = [];
        $currentGroup = null;

        foreach ($fields as $field) {
            $groupKey = $field['group_key'] ?? '_ungrouped_' . $field['field_key'];

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'label'  => $field['group_label'] ?? null,
                    'fields' => [],
                ];
            }
            $groups[$groupKey]['fields'][] = $field;
        }

        $html = '';
        foreach ($groups as $groupKey => $group) {
            if ($group['label'] && !str_starts_with($groupKey, '_ungrouped_')) {
                $html .= '<div class="edivi__field-group" data-group="' . htmlspecialchars($groupKey) . '">';
                $html .= '<div class="edivi__field-group-label">' . htmlspecialchars($group['label']) . '</div>';
            }

            $html .= '<div class="row">';
            foreach ($group['fields'] as $field) {
                $colClass = $this->getColumnClass($field['column_width'] ?? 'full');
                $html .= '<div class="' . $colClass . '">';
                $html .= $this->renderField($field, $daten, $istFreigegeben);
                $html .= '</div>';
            }
            $html .= '</div>';

            if ($group['label'] && !str_starts_with($groupKey, '_ungrouped_')) {
                $html .= '</div>';
            }
        }

        return $html;
    }

    /**
     * Render a single field based on its type and widget.
     */
    public function renderField(array $field, array $daten, bool $istFreigegeben = false): string
    {
        // Composite fields with custom widgets get their own partial
        if ($field['field_type'] === 'composite' && !empty($field['widget'])) {
            return $this->renderWidget($field, $daten, $istFreigegeben);
        }

        // Special widget overrides for standard field types
        if (!empty($field['widget'])) {
            $widgetPath = $this->getWidgetPath($field['widget']);
            if ($widgetPath && file_exists($widgetPath)) {
                return $this->renderWidget($field, $daten, $istFreigegeben);
            }
        }

        $fieldKey = $field['field_key'];
        $value = $daten[$fieldKey] ?? $field['default_value'] ?? null;
        $disabled = $istFreigegeben ? ' disabled' : '';
        $readonly = $istFreigegeben ? ' readonly' : '';
        $required = $field['is_required'] ? ' data-required="1"' : '';

        return match ($field['field_type']) {
            'radio'             => $this->renderRadio($field, $value, $disabled, $required),
            'checkbox'          => $this->renderCheckbox($field, $value, $disabled, $required),
            'checkbox_group'    => $this->renderCheckboxGroup($field, $value, $disabled, $required),
            'text'              => $this->renderTextInput($field, $value, $disabled . $readonly, $required),
            'textarea'          => $this->renderTextarea($field, $value, $disabled . $readonly, $required),
            'number'            => $this->renderNumberInput($field, $value, $disabled . $readonly, $required),
            'date'              => $this->renderDateInput($field, $value, $disabled . $readonly, $required),
            'time'              => $this->renderTimeInput($field, $value, $disabled . $readonly, $required),
            'select'            => $this->renderSelect($field, $value, $disabled, $required),
            'custom_dropdown'   => $this->renderCustomDropdown($field, $value, $disabled, $required),
            'json_multi_select' => $this->renderJsonMultiSelect($field, $value, $disabled, $required),
            'hidden'            => $this->renderHidden($field, $value),
            default             => $this->renderTextInput($field, $value, $disabled . $readonly, $required),
        };
    }

    // ──── Field Type Renderers ────

    private function renderRadio(array $field, $value, string $attrs, string $required): string
    {
        $options = $this->getOptions($field);
        if (empty($options)) {
            return '';
        }

        $key = htmlspecialchars($field['field_key']);
        $html = '<div class="d-flex flex-column edivi__interactbutton"' . $required . '>';

        if ($field['label']) {
            $html .= '<small class="edivi__field-label text-muted mb-1">' . htmlspecialchars($field['label']) . '</small>';
        }

        foreach ($options as $opt) {
            $optValue = $opt['value'];
            $optLabel = $opt['label'];
            $id = $key . '-' . $optValue;
            $checked = ($value !== null && (string)$value === (string)$optValue) ? ' checked' : '';
            $unauffClass = (isset($opt['is_normal']) && $opt['is_normal']) ? ' class="edivi__unauffaellig"' : '';

            $html .= '<input type="radio" class="btn-check" id="' . $id . '" name="' . $key . '" value="' . htmlspecialchars($optValue) . '"' . $checked . $attrs . ' autocomplete="off">';
            $html .= '<label for="' . $id . '"' . $unauffClass . '>' . htmlspecialchars($optLabel) . '</label>';
        }

        $html .= '</div>';
        return $html;
    }

    private function renderCheckbox(array $field, $value, string $attrs, string $required): string
    {
        $key = htmlspecialchars($field['field_key']);
        $checked = ($value && $value != '0') ? ' checked' : '';

        $html = '<div class="form-check"' . $required . '>';
        $html .= '<input class="form-check-input" type="checkbox" id="' . $key . '" name="' . $key . '" value="1"' . $checked . $attrs . '>';
        $html .= '<label class="form-check-label" for="' . $key . '">' . htmlspecialchars($field['label']) . '</label>';
        $html .= '</div>';

        return $html;
    }

    private function renderCheckboxGroup(array $field, $value, string $attrs, string $required): string
    {
        $options = $this->getOptions($field);
        $selectedValues = is_string($value) ? json_decode($value, true) : ($value ?? []);
        if (!is_array($selectedValues)) {
            $selectedValues = [];
        }

        $key = htmlspecialchars($field['field_key']);
        $html = '<div class="edivi__checkbox-group"' . $required . '>';

        if ($field['label']) {
            $html .= '<small class="edivi__field-label text-muted mb-1">' . htmlspecialchars($field['label']) . '</small>';
        }

        foreach ($options as $opt) {
            $optId = $key . '_' . $opt['value'];
            $checked = in_array($opt['value'], $selectedValues) ? ' checked' : '';
            $html .= '<div class="form-check">';
            $html .= '<input class="form-check-input" type="checkbox" id="' . $optId . '" name="' . $key . '[]" value="' . htmlspecialchars($opt['value']) . '"' . $checked . $attrs . '>';
            $html .= '<label class="form-check-label" for="' . $optId . '">' . htmlspecialchars($opt['label']) . '</label>';
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    private function renderTextInput(array $field, $value, string $attrs, string $required): string
    {
        $key = htmlspecialchars($field['field_key']);
        $placeholder = $field['placeholder'] ? ' placeholder="' . htmlspecialchars($field['placeholder']) . '"' : '';

        $html = '<div class="edivi__field-wrap"' . $required . '>';
        if ($field['label']) {
            $html .= '<label for="' . $key . '" class="form-label edivi__field-label">' . htmlspecialchars($field['label']) . '</label>';
        }
        $html .= '<input type="text" class="form-control" id="' . $key . '" name="' . $key . '" value="' . htmlspecialchars($value ?? '') . '"' . $placeholder . $attrs . '>';
        if ($field['hint_text']) {
            $html .= '<small class="form-text text-muted">' . htmlspecialchars($field['hint_text']) . '</small>';
        }
        $html .= '</div>';

        return $html;
    }

    private function renderTextarea(array $field, $value, string $attrs, string $required): string
    {
        $key = htmlspecialchars($field['field_key']);
        $placeholder = $field['placeholder'] ? ' placeholder="' . htmlspecialchars($field['placeholder']) . '"' : '';

        $html = '<div class="edivi__field-wrap"' . $required . '>';
        if ($field['label']) {
            $html .= '<label for="' . $key . '" class="form-label edivi__field-label">' . htmlspecialchars($field['label']) . '</label>';
        }
        $html .= '<textarea class="form-control" id="' . $key . '" name="' . $key . '" rows="3"' . $placeholder . $attrs . '>' . htmlspecialchars($value ?? '') . '</textarea>';
        $html .= '</div>';

        return $html;
    }

    private function renderNumberInput(array $field, $value, string $attrs, string $required): string
    {
        $key = htmlspecialchars($field['field_key']);
        $suffix = $field['input_suffix'] ?? null;
        $min = $field['min_value'] ? ' min="' . htmlspecialchars($field['min_value']) . '"' : '';
        $max = $field['max_value'] ? ' max="' . htmlspecialchars($field['max_value']) . '"' : '';

        $html = '<div class="edivi__field-wrap"' . $required . '>';
        if ($field['label']) {
            $html .= '<label for="' . $key . '" class="form-label edivi__field-label">' . htmlspecialchars($field['label']) . '</label>';
        }

        if ($suffix) {
            $html .= '<div class="input-group">';
        }

        $html .= '<input type="text" inputmode="numeric" class="form-control" id="' . $key . '" name="' . $key . '" value="' . htmlspecialchars($value ?? '') . '"' . $min . $max . $attrs . '>';

        if ($suffix) {
            $html .= '<span class="input-group-text">' . htmlspecialchars($suffix) . '</span>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    private function renderDateInput(array $field, $value, string $attrs, string $required): string
    {
        $key = htmlspecialchars($field['field_key']);
        $displayValue = $value;
        if ($value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $displayValue = date('d.m.Y', strtotime($value));
        }

        $html = '<div class="edivi__field-wrap"' . $required . '>';
        if ($field['label']) {
            $html .= '<label for="' . $key . '" class="form-label edivi__field-label">' . htmlspecialchars($field['label']) . '</label>';
        }
        $html .= '<input type="text" class="form-control force-german-date" id="' . $key . '" name="' . $key . '" value="' . htmlspecialchars($displayValue ?? '') . '" placeholder="TT.MM.JJJJ"' . $attrs . '>';
        $html .= '</div>';

        return $html;
    }

    private function renderTimeInput(array $field, $value, string $attrs, string $required): string
    {
        $key = htmlspecialchars($field['field_key']);

        $html = '<div class="edivi__field-wrap"' . $required . '>';
        if ($field['label']) {
            $html .= '<label for="' . $key . '" class="form-label edivi__field-label">' . htmlspecialchars($field['label']) . '</label>';
        }
        $html .= '<input type="text" class="form-control force-24h-time" id="' . $key . '" name="' . $key . '" value="' . htmlspecialchars($value ?? '') . '" placeholder="HH:MM"' . $attrs . '>';
        $html .= '</div>';

        return $html;
    }

    private function renderSelect(array $field, $value, string $attrs, string $required): string
    {
        $options = $this->getOptions($field);
        $key = htmlspecialchars($field['field_key']);

        $html = '<div class="edivi__field-wrap"' . $required . '>';
        if ($field['label']) {
            $html .= '<label for="' . $key . '" class="form-label edivi__field-label">' . htmlspecialchars($field['label']) . '</label>';
        }
        $html .= '<select class="form-select" id="' . $key . '" name="' . $key . '"' . $attrs . '>';
        $html .= '<option value="">-- Auswahl --</option>';

        foreach ($options as $opt) {
            $selected = ($value !== null && (string)$value === (string)$opt['value']) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($opt['value']) . '"' . $selected . '>' . htmlspecialchars($opt['label']) . '</option>';
        }

        $html .= '</select>';
        $html .= '</div>';

        return $html;
    }

    private function renderCustomDropdown(array $field, $value, string $attrs, string $required): string
    {
        $options = $this->getOptions($field);
        $key = htmlspecialchars($field['field_key']);

        $html = '<div class="edivi__field-wrap"' . $required . '>';
        if ($field['label']) {
            $html .= '<label class="form-label edivi__field-label">' . htmlspecialchars($field['label']) . '</label>';
        }
        $html .= '<div class="enotf-dropdown-wrapper" data-name="' . $key . '" data-value="' . htmlspecialchars($value ?? '') . '">';
        $html .= '<div class="enotf-dropdown-container">';
        $html .= '<div class="enotf-dropdown-selected">-- Auswahl --</div>';
        $html .= '<div class="enotf-dropdown-options">';
        $html .= '<input type="text" class="enotf-dropdown-search" placeholder="Suchen...">';

        foreach ($options as $opt) {
            $selected = ($value !== null && (string)$value === (string)$opt['value']) ? ' selected' : '';
            $html .= '<div class="enotf-dropdown-option' . $selected . '" data-value="' . htmlspecialchars($opt['value']) . '">' . htmlspecialchars($opt['label']) . '</div>';
        }

        $html .= '</div></div>';
        $html .= '<input type="hidden" name="' . $key . '" value="' . htmlspecialchars($value ?? '') . '">';
        $html .= '</div></div>';

        return $html;
    }

    private function renderJsonMultiSelect(array $field, $value, string $attrs, string $required): string
    {
        $options = $this->getOptions($field);
        $selectedValues = is_string($value) ? json_decode($value, true) : ($value ?? []);
        if (!is_array($selectedValues)) {
            $selectedValues = [];
        }

        $key = htmlspecialchars($field['field_key']);
        $html = '<div class="d-flex flex-column edivi__interactbutton"' . $required . '>';

        if ($field['label']) {
            $html .= '<small class="edivi__field-label text-muted mb-1">' . htmlspecialchars($field['label']) . '</small>';
        }

        foreach ($options as $opt) {
            $optId = $key . '_' . $opt['value'];
            $checked = in_array($opt['value'], $selectedValues) ? ' checked' : '';
            $html .= '<input type="checkbox" class="btn-check" id="' . $optId . '" name="' . $key . '[]" value="' . htmlspecialchars($opt['value']) . '"' . $checked . $attrs . ' autocomplete="off">';
            $html .= '<label for="' . $optId . '">' . htmlspecialchars($opt['label']) . '</label>';
        }

        $html .= '</div>';
        return $html;
    }

    private function renderHidden(array $field, $value): string
    {
        return '<input type="hidden" name="' . htmlspecialchars($field['field_key']) . '" value="' . htmlspecialchars($value ?? '') . '">';
    }

    // ──── Widget Rendering ────

    private function renderWidget(array $field, array $daten, bool $istFreigegeben): string
    {
        $widgetPath = $this->getWidgetPath($field['widget']);
        if (!$widgetPath || !file_exists($widgetPath)) {
            return '<div class="text-muted">Widget "' . htmlspecialchars($field['widget']) . '" nicht gefunden.</div>';
        }

        // Pass standard variables to widget partial
        $enr = $daten['enr'] ?? '';
        $fieldDef = $field;
        $pdo = $this->pdo;
        $basePath = $this->basePath;

        ob_start();
        include $widgetPath;
        return ob_get_clean();
    }

    private function getWidgetPath(string $widget): ?string
    {
        $widgetsDir = dirname(__DIR__, 2) . '/assets/components/enotf/widgets/';
        $path = $widgetsDir . $widget . '.php';
        return file_exists($path) ? $path : null;
    }

    // ──── Helpers ────

    private function getOptions(array $field): array
    {
        $json = $field['override_options_json'] ?? $field['options_json'] ?? null;
        if (!$json) {
            return [];
        }

        $parsed = json_decode($json, true);
        if (!$parsed) {
            return [];
        }

        return $parsed['values'] ?? $parsed;
    }

    private function getColumnClass(string $width): string
    {
        return match ($width) {
            'half'    => 'col-12 col-md-6',
            'third'   => 'col-12 col-md-4',
            'quarter' => 'col-12 col-md-3',
            default   => 'col-12',
        };
    }
}
