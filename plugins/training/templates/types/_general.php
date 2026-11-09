<?php
/**
 * Felder einer Lehrgangsart, für Anlegen und den Reiter Allgemein.
 *
 * @var array<string, mixed> $form
 * @var array{targets: array<string, string>, qualifications: array<string, array<string, string>>} $options
 */

use EmergencyForge\Training\EnrollmentMode;
use Plugin\Training\Models\CourseType;

$qualSelect = static function (string $name, string $id, string $value, array $targets): string {
    $html = '<select id="' . $id . '" name="' . $name . '" class="ignis-input" data-custom-dropdown="true"><option value="">Keine</option>';
    foreach ($targets as $key => $label) {
        $html .= '<option value="' . htmlspecialchars($key) . '"' . ($key === $value ? ' selected' : '') . '>' . htmlspecialchars($label) . '</option>';
    }

    return $html . '</select>';
};
?>
<div class="grid gap-4">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="md:col-span-2">
            <label for="type-name" class="ignis-field__label">Name <span class="ignis-field__required">*</span></label>
            <input type="text" id="type-name" name="name" class="ignis-input" maxlength="150" required value="<?= htmlspecialchars((string) $form['name']) ?>" placeholder="Rettungssanitäter">
        </div>
        <div>
            <label for="type-short" class="ignis-field__label">Kürzel <span class="ignis-field__required">*</span></label>
            <input type="text" id="type-short" name="short" class="ignis-input" maxlength="20" required value="<?= htmlspecialchars((string) $form['short']) ?>" placeholder="RS">
            <p class="ignis-field__hint">Daraus wird der Titel: „RS-Lehrgang 3/2026“.</p>
        </div>
    </div>
    <div>
        <label for="type-description" class="ignis-field__label">Beschreibung</label>
        <textarea id="type-description" name="description" class="ignis-input" rows="3"><?= htmlspecialchars((string) $form['description']) ?></textarea>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
            <label for="type-area" class="ignis-field__label">Bereich</label>
            <select id="type-area" name="area" class="ignis-input" data-custom-dropdown="true">
                <?php foreach (CourseType::AREAS as $key => $label): ?>
                    <option value="<?= $key ?>"<?= $form['area'] === $key ? ' selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="type-enrollment" class="ignis-field__label">Anmeldung</label>
            <select id="type-enrollment" name="enrollment_mode" class="ignis-input" data-custom-dropdown="true">
                <?php foreach (EnrollmentMode::cases() as $mode): ?>
                    <option value="<?= $mode->value ?>"<?= $form['enrollment_mode'] === $mode->value ? ' selected' : '' ?>><?= htmlspecialchars($mode->label()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="type-validity" class="ignis-field__label">Gültigkeit in Monaten</label>
            <input type="number" id="type-validity" name="validity_months" class="ignis-input" min="1" value="<?= htmlspecialchars((string) $form['validity_months']) ?>">
            <p class="ignis-field__hint">Leer heißt unbegrenzt.</p>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <label for="type-start" class="ignis-field__label">Qualifikation bei Beginn</label>
            <?= $qualSelect('start_qualification', 'type-start', (string) $form['start_qualification'], $options['targets']) ?>
            <p class="ignis-field__hint">Bekommt jeder, wenn der Lehrgang auf „Läuft“ geht, etwa RettSan i.A. Zur Wahl stehen RD-Stufen mit „Zertifiziert?“.</p>
        </div>
        <div>
            <label for="type-pass" class="ignis-field__label">Qualifikation bei Bestehen</label>
            <?= $qualSelect('pass_qualification', 'type-pass', (string) $form['pass_qualification'], $options['targets']) ?>
            <p class="ignis-field__hint">Wird mit dem Abschluss vergeben.</p>
        </div>
    </div>
    <div>
        <span class="ignis-field__label" id="type-prereq-label">Voraussetzungen</span>
        <?php
        $prereqOptions = [];
        foreach ($options['qualifications'] as $group => $items) {
            foreach ($items as $key => $label) {
                $prereqOptions[] = ['value' => $key, 'label' => $label . ' · ' . $group];
            }
        }
        ?>
        <div data-ignis-multi-select data-name="prerequisites[]" aria-labelledby="type-prereq-label" data-placeholder="Qualifikation suchen"
             data-options="<?= htmlspecialchars((string) json_encode($prereqOptions, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>"
             data-value="<?= htmlspecialchars(implode(',', (array) $form['prerequisites'])) ?>"></div>
        <p class="ignis-field__hint">Wer sich selbst anmeldet, braucht alle. Eine höhere RD- oder FW-Stufe zählt mit. Die Leitung kann trotzdem eintragen.</p>
    </div>
    <label class="flex items-center gap-2">
        <span class="ignis-switch"><input type="checkbox" name="is_active" value="1"<?= $form['is_active'] ? ' checked' : '' ?>><span></span></span>
        Aktiv: neue Lehrgänge dieser Art lassen sich anlegen
    </label>
</div>
<script type="module" src="<?= defined('BASE_PATH') ? BASE_PATH : '/' ?>assets/js/ui/multi-select.js"></script>
