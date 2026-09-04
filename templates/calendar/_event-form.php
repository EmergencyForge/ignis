<?php
/**
 * Felder des Terminformulars. Zweimal im Einsatz: in templates/calendar/
 * create.php als Formular (Seite oder Drawer) und in index.php als
 * <template> für den Bearbeiten-Dialog, den calendar.js mit den Daten
 * des Termins füllt. Die Dynamik (Sichtbarkeit, Serie, Ganztägig) bindet
 * assets/js/pages/calendar-form.js an [data-calendar-event-form].
 *
 * Nach einem gescheiterten Post kommt die Eingabe über old() zurück; im
 * <template> der Kalenderseite gibt es keinen Bag, dann gelten die
 * Standardwerte. $eventFormStart/$eventFormEnd (ISO, optional) füllen die
 * Zeitfelder vor, etwa nach einem Klick auf einen Tag.
 *
 * @var array<string,string>                                        $categories
 * @var array<int,string>                                           $colors
 * @var array<int,array<string,mixed>>                              $roles
 * @var \Illuminate\Support\Collection<int,\App\Models\Personnel>   $mitarbeiter
 * @var string|null                                                 $eventFormStart
 * @var string|null                                                 $eventFormEnd
 */

$colorLabelsDe = [
    'orange' => 'Orange',
    'blue'   => 'Blau',
    'green'  => 'Grün',
    'red'    => 'Rot',
    'purple' => 'Lila',
    'gray'   => 'Grau',
];

// JSON-Optionsliste fuer den Multi-Select. Mitarbeiter mit
// Dienstnummer-Suffix damit zwei Maxe Mustermanns auseinander-
// gehalten werden koennen.
$rolesOptions = array_map(static fn ($r) => [
    'value' => (int) $r['id'],
    'label' => (string) $r['name'],
], $roles);
$mitarbeiterOptions = $mitarbeiter->map(static fn ($m) => [
    'value' => (int) $m->id,
    'label' => trim(($m->fullname ?? '') . ' (' . ($m->dienstnr ?? '—') . ')'),
])->all();

$oldList = static function (string $field): string {
    $values = old($field, []);
    return is_array($values) ? implode(',', array_map('intval', $values)) : '';
};
$oldStart = (string) old('starts_at', $eventFormStart ?? '');
$oldEnd   = (string) old('ends_at', $eventFormEnd ?? '');
$oldAllDay = (string) old('all_day', '') !== '';
$oldVisibility = (string) old('visibility', 'attendees');

// Serie: die Regel liegt als RRULE im Hidden-Feld; für die Felder
// Frequenz, Intervall und Wochentage wird sie hier zurückgelesen.
$oldRule = (string) old('recurrence_rule', '');
$ruleFreq = 'WEEKLY';
$ruleInterval = '1';
$ruleDays = [];
foreach (explode(';', $oldRule) as $rulePart) {
    [$ruleKey, $ruleValue] = array_pad(explode('=', $rulePart, 2), 2, '');
    if ($ruleKey === 'FREQ' && $ruleValue !== '') {
        $ruleFreq = $ruleValue;
    } elseif ($ruleKey === 'INTERVAL' && $ruleValue !== '') {
        $ruleInterval = $ruleValue;
    } elseif ($ruleKey === 'BYDAY' && $ruleValue !== '') {
        $ruleDays = explode(',', $ruleValue);
    }
}
?>
        <div class="grid grid-cols-1 gap-3">
            <div>
                <label for="evt-title" class="ignis-field__label">Titel <span class="ignis-field__required">*</span></label>
                <input type="text" id="evt-title" name="title" class="ignis-input" required maxlength="160" placeholder="Wofür ist der Termin?" value="<?= htmlspecialchars((string) old('title')) ?>">
            </div>

            <div class="ignis-checkbox">
                <input type="checkbox" id="evt-allday" name="all_day" value="1" data-allday-toggle<?= $oldAllDay ? ' checked' : '' ?>>
                <label for="evt-allday">Ganztägig</label>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="ignis-field__label">Start <span class="ignis-field__required">*</span></label>
                    <div data-picker-slot="starts_at">
                        <div data-ignis-datetimepicker data-name="starts_at" data-required="true"<?= $oldStart !== '' ? ' data-value="' . htmlspecialchars($oldStart) . '"' : '' ?>></div>
                    </div>
                </div>
                <div>
                    <label class="ignis-field__label">Ende <span class="ignis-field__required">*</span></label>
                    <div data-picker-slot="ends_at">
                        <div data-ignis-datetimepicker data-name="ends_at" data-required="true"<?= $oldEnd !== '' ? ' data-value="' . htmlspecialchars($oldEnd) . '"' : '' ?>></div>
                    </div>
                </div>
            </div>

            <div>
                <label for="evt-location" class="ignis-field__label">Ort</label>
                <input type="text" id="evt-location" name="location" class="ignis-input" maxlength="255" value="<?= htmlspecialchars((string) old('location')) ?>">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="evt-category" class="ignis-field__label">Kategorie</label>
                    <select id="evt-category" name="category" class="ignis-input">
                        <?php foreach ($categories as $key => $label): ?>
                            <?php if ($key === 'absence') continue; /* nur via Antrag-Sync */ ?>
                            <option value="<?= htmlspecialchars($key) ?>"<?= (string) old('category', 'general') === $key ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="evt-color" class="ignis-field__label">Farbe</label>
                    <select id="evt-color" name="color" class="ignis-input">
                        <?php foreach ($colors as $color): ?>
                            <option value="<?= htmlspecialchars($color) ?>"<?= (string) old('color', 'orange') === $color ? ' selected' : '' ?>><?= htmlspecialchars($colorLabelsDe[$color] ?? ucfirst($color)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label for="evt-visibility" class="ignis-field__label">Sichtbarkeit</label>
                <select id="evt-visibility" name="visibility" class="ignis-input">
                    <?php foreach (['private' => 'Privat (nur ich)', 'attendees' => 'Eingeladene Mitarbeiter', 'role' => 'Bestimmte Rolle', 'all' => 'Alle (öffentlich)'] as $visKey => $visLabel): ?>
                        <option value="<?= $visKey ?>"<?= $oldVisibility === $visKey ? ' selected' : '' ?>><?= $visLabel ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div data-visibility-role-row style="display:none;">
                <label class="ignis-field__label">Rollen</label>
                <div data-ignis-multi-select
                     data-name="visibility_role_ids[]"
                     data-value="<?= htmlspecialchars($oldList('visibility_role_ids')) ?>"
                     data-options='<?= htmlspecialchars(json_encode($rolesOptions, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'
                     data-placeholder="Rolle suchen…"
                     data-empty-text="Keine Rolle gefunden"></div>
                <small class="form-hint">Mehrere Rollen wählbar — alle Mitglieder sehen den Termin</small>

                <div class="ignis-checkbox mt-2">
                    <input type="checkbox" id="evt-track-attendance" name="track_attendance" value="1" data-track-attendance<?= (string) old('track_attendance', '') !== '' ? ' checked' : '' ?>>
                    <label for="evt-track-attendance">Teilnehmer-Antworten verfolgen (Rollenmitglieder können zusagen/absagen)</label>
                </div>
            </div>

            <div data-visibility-attendees-row>
                <label class="ignis-field__label">Eingeladene Mitarbeiter</label>
                <div data-ignis-multi-select
                     data-name="attendees[]"
                     data-value="<?= htmlspecialchars($oldList('attendees')) ?>"
                     data-options='<?= htmlspecialchars(json_encode($mitarbeiterOptions, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'
                     data-placeholder="Mitarbeiter suchen…"
                     data-empty-text="Keine Mitarbeiter gefunden"></div>
            </div>

            <hr class="my-2">

            <div class="ignis-checkbox">
                <input type="checkbox" id="evt-recurring" data-recurrence-toggle<?= $oldRule !== '' ? ' checked' : '' ?>>
                <label for="evt-recurring">Wiederholt sich</label>
            </div>

            <div data-recurrence-row style="display:none;" class="grid grid-cols-2 gap-3">
                <div>
                    <label for="evt-rrule-freq" class="ignis-field__label">Frequenz</label>
                    <select id="evt-rrule-freq" data-rrule="freq" class="ignis-input">
                        <?php foreach (['DAILY' => 'Täglich', 'WEEKLY' => 'Wöchentlich', 'MONTHLY' => 'Monatlich'] as $freqKey => $freqLabel): ?>
                            <option value="<?= $freqKey ?>"<?= $ruleFreq === $freqKey ? ' selected' : '' ?>><?= $freqLabel ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="evt-rrule-interval" class="ignis-field__label">Intervall</label>
                    <input type="number" id="evt-rrule-interval" data-rrule="interval" class="ignis-input" min="1" value="<?= htmlspecialchars($ruleInterval) ?>">
                </div>
                <div class="col-span-2" data-rrule-byday-row>
                    <label class="ignis-field__label">Wochentage (nur wöchentlich)</label>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach (['MO' => 'Mo', 'TU' => 'Di', 'WE' => 'Mi', 'TH' => 'Do', 'FR' => 'Fr', 'SA' => 'Sa', 'SU' => 'So'] as $code => $label): ?>
                            <label class="ignis-chip ignis-chip--toggle">
                                <input type="checkbox" data-rrule-byday="<?= $code ?>"<?= in_array($code, $ruleDays, true) ? ' checked' : '' ?>><?= $label ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-span-2">
                    <label for="evt-rrule-until" class="ignis-field__label">Bis Datum (optional)</label>
                    <input type="date" id="evt-rrule-until" name="recurrence_until" class="ignis-input" value="<?= htmlspecialchars((string) old('recurrence_until')) ?>">
                </div>
                <input type="hidden" name="recurrence_rule" data-rrule-output value="<?= htmlspecialchars($oldRule) ?>">
            </div>

            <div>
                <label for="evt-description" class="ignis-field__label">Beschreibung</label>
                <textarea id="evt-description" name="description" class="ignis-input" rows="3" maxlength="2000"><?= htmlspecialchars((string) old('description')) ?></textarea>
            </div>
        </div>
