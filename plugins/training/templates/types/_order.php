<?php
/**
 * Reiter Prüfungsordnung: Theorie- und Praxisteile in ihrer Reihenfolge.
 * Zeilen für Regeln, Gruppen und Kriterien legt training.js aus den
 * Vorlagen an (Platzhalter __i__ und __c__).
 *
 * @var \Plugin\Training\Models\CourseType       $type
 * @var list<\Plugin\Training\Models\Exam>       $parts
 * @var list<\Plugin\Training\Models\Topic>      $topics
 * @var list<\Plugin\Training\Models\Question>   $questions
 * @var array<int, int>                          $active  Thema => aktive Fragen
 * @var string                                   $typeUrl
 */

use EmergencyForge\Training\Exam\ReviewMode;

$json     = static fn (mixed $v): string => htmlspecialchars((string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES);
$names    = [];
foreach ($topics as $t) {
    $names[$t->id] = $t->name;
}
$qOptions = array_values(array_map(static fn ($q): array => [
    'value' => $q->id,
    'label' => ($names[$q->topic_id] ?? 'Ohne Thema') . ': ' . mb_strimwidth($q->body, 0, 90, '…'),
], array_filter($questions, static fn ($q): bool => $q->is_active)));
$topicSelect = static function (string $name, ?int $value) use ($topics, $active): string {
    $html = '<select name="' . $name . '" class="ignis-input" data-custom-dropdown="true" aria-label="Thema">';
    foreach ($topics as $t) {
        $html .= '<option value="' . (int) $t->id . '"' . ($t->id === $value ? ' selected' : '') . '>' . htmlspecialchars($t->name) . ' (' . (int) ($active[$t->id] ?? 0) . ' aktiv)</option>';
    }

    return $html . '</select>';
};
$multi = static fn (string $name, array $value, string $label): string => '<div data-ignis-multi-select data-name="' . $name . '" aria-label="' . htmlspecialchars($label) . '" data-placeholder="Frage suchen" data-options="' . $json($qOptions) . '" data-value="' . htmlspecialchars(implode(',', array_map('intval', $value))) . '"></div>';
$switch = static fn (string $name, bool $on, string $label): string => '<label class="flex items-center gap-2"><span class="ignis-switch"><input type="checkbox" name="' . $name . '" value="1"' . ($on ? ' checked' : '') . '><span></span></span> ' . htmlspecialchars($label) . '</label>';
$number = static fn (string $name, string $label, mixed $value, string $id, string $hint = '', string $attrs = 'min="0"'): string => '<div><label for="' . $id . '" class="ignis-field__label">' . htmlspecialchars($label) . '</label><input type="number" id="' . $id . '" name="' . $name . '" class="ignis-input" ' . $attrs . ' value="' . htmlspecialchars((string) $value) . '">' . ($hint !== '' ? '<p class="ignis-field__hint">' . htmlspecialchars($hint) . '</p>' : '') . '</div>';
$ruleRow = static fn (string $i, ?int $topic, int $count): string => '<tr data-tr-row><td>' . $topicSelect('topics[' . $i . '][topic_id]', $topic) . '</td>'
    . '<td class="ignis-table__num"><input type="number" name="topics[' . $i . '][count]" class="ignis-input tr-num" min="1" value="' . $count . '" aria-label="Anzahl Fragen"></td>'
    . '<td class="ignis-table__actions"><button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--icon ignis-btn--sm" data-tr-remove aria-label="Regel entfernen" data-ignis-tooltip="Entfernen"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></td></tr>';
$criterionRow = static fn (string $g, string $c, array $crit): string => '<div class="tr-crit" data-tr-row>'
    . '<input type="hidden" name="groups[' . $g . '][criteria][' . $c . '][key]" value="' . htmlspecialchars((string) ($crit['key'] ?? '')) . '">'
    . '<input type="text" name="groups[' . $g . '][criteria][' . $c . '][label]" class="ignis-input tr-crit__label" maxlength="300" placeholder="Kriterium" aria-label="Kriterium" value="' . htmlspecialchars((string) ($crit['label'] ?? '')) . '">'
    . '<input type="number" name="groups[' . $g . '][criteria][' . $c . '][points]" class="ignis-input tr-num" min="0.5" step="0.5" placeholder="ja/nein" aria-label="Punkte, leer für erfüllt oder nicht erfüllt" data-ignis-tooltip="Leer: erfüllt oder nicht erfüllt" value="' . htmlspecialchars(isset($crit['points']) ? (string) $crit['points'] : '') . '">'
    . '<label class="ignis-checkbox"><input type="checkbox" name="groups[' . $g . '][criteria][' . $c . '][knockout]" value="1"' . (!empty($crit['knockout']) ? ' checked' : '') . '><span>Muss erfüllt sein</span></label>'
    . '<button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--icon ignis-btn--sm" data-tr-remove aria-label="Kriterium entfernen" data-ignis-tooltip="Entfernen"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>';
$groupBlock = static function (string $g, array $group) use ($criterionRow): string {
    $rows = '';
    foreach (array_values((array) ($group['criteria'] ?? [])) as $c => $crit) {
        $rows .= $criterionRow($g, (string) $c, (array) $crit);
    }

    return '<div class="tr-group" data-tr-row data-tr-repeater>'
        . '<div class="flex gap-2 items-center"><input type="text" name="groups[' . $g . '][title]" class="ignis-input tr-group__title" maxlength="150" placeholder="Gruppe, z. B. Vorbereitung" aria-label="Gruppe" value="' . htmlspecialchars((string) ($group['title'] ?? '')) . '">'
        . '<button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--icon ignis-btn--sm" data-tr-remove aria-label="Gruppe entfernen" data-ignis-tooltip="Gruppe entfernen"><i class="fa-solid fa-trash" aria-hidden="true"></i></button></div>'
        . '<div data-tr-rows>' . $rows . '</div>'
        . '<template data-tr-template data-placeholder="__c__">' . $criterionRow($g, '__c__', []) . '</template>'
        . '<button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm mt-1" data-tr-add><i class="fa-solid fa-plus" aria-hidden="true"></i> Kriterium</button>'
        . '</div>';
};
?>
<div class="flex items-center justify-between gap-3 mb-3 flex-wrap">
    <p class="ignis-detail__muted m-0">Bestanden ist der Lehrgang, wenn alle Pflicht-Teile bestanden sind. Die Teile laufen in dieser Reihenfolge.</p>
    <form method="post" action="<?= $typeUrl ?>/exams" class="flex gap-2">
        <?= csrf_field() ?>
        <button type="submit" name="kind" value="practical" class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Praxisteil</button>
        <button type="submit" name="kind" value="theory" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Theorieteil</button>
    </form>
</div>

<?php if ($parts === []): ?>
    <div class="ignis-card"><div class="ignis-card__body"><p class="m-0">Noch keine Prüfung. Ein Theorieteil zieht Fragen aus dem Fragenpool, ein Praxisteil bewertet ein Prüfer mit einem Bogen.</p></div></div>
<?php endif; ?>

<?php foreach ($parts as $index => $exam): ?>
    <?php
    $id       = (int) $exam->id;
    $settings = $exam->examSettings();
    $s        = $exam->settings ?? [];
    $bp       = $exam->blueprint ?? [];
    $theory   = $exam->isTheory();
    $formId   = 'exam-' . $id;
    ?>
    <form method="post" action="<?= $base ?>training/exams/<?= $id ?>/move" id="exam-move-<?= $id ?>" hidden><?= csrf_field() ?></form>
    <form method="post" action="<?= $base ?>training/exams/<?= $id ?>/delete" id="exam-delete-<?= $id ?>" hidden onsubmit="<?= confirm_attr('Prüfungsteil „' . $exam->title . '“ löschen?') ?>"><?= csrf_field() ?></form>
    <form method="post" action="<?= $base ?>training/exams/<?= $id ?>" id="<?= $formId ?>" class="tr-part">
        <?= csrf_field() ?>
        <div class="tr-part__head">
            <span class="ignis-glyph ignis-glyph--sm"><i class="fa-solid <?= $theory ? 'fa-list-check' : 'fa-clipboard-check' ?>" aria-hidden="true"></i></span>
            <span class="tr-meta"><?= $index + 1 ?></span>
            <label for="<?= $formId ?>-title" class="ignis-sr-only">Titel</label>
            <input type="text" id="<?= $formId ?>-title" name="title" class="ignis-input tr-part__title" maxlength="150" value="<?= htmlspecialchars($exam->title) ?>">
            <?= $switch('required', $exam->required, 'Pflicht') ?>
            <button type="submit" form="exam-move-<?= $id ?>" name="direction" value="up" class="ignis-btn ignis-btn--ghost ignis-btn--icon ignis-btn--sm" aria-label="Nach oben" data-ignis-tooltip="Nach oben"<?= $index === 0 ? ' disabled' : '' ?>><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></button>
            <button type="submit" form="exam-move-<?= $id ?>" name="direction" value="down" class="ignis-btn ignis-btn--ghost ignis-btn--icon ignis-btn--sm" aria-label="Nach unten" data-ignis-tooltip="Nach unten"<?= $index === count($parts) - 1 ? ' disabled' : '' ?>><i class="fa-solid fa-arrow-down" aria-hidden="true"></i></button>
            <button type="submit" form="exam-delete-<?= $id ?>" class="ignis-btn ignis-btn--ghost ignis-btn--icon ignis-btn--sm" aria-label="Löschen" data-ignis-tooltip="Löschen"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
        </div>
        <div class="tr-part__body">
            <?php if ($theory): ?>
                <?php $useVariants = ($bp['variants'] ?? []) !== []; ?>
                <h4>Zusammenstellung</h4>
                <div data-tr-variants-off<?= $useVariants ? ' hidden' : '' ?>>
                    <div data-tr-repeater>
                        <?php if ($topics === []): ?>
                            <p class="ignis-detail__muted">Für Regeln braucht der Fragenpool Themen.</p>
                        <?php endif; ?>
                        <table class="ignis-table">
                            <thead><tr><th scope="col">Zufällig aus Thema</th><th scope="col" class="ignis-table__num">Fragen</th><th scope="col"><span class="ignis-sr-only">Aktionen</span></th></tr></thead>
                            <tbody data-tr-rows>
                                <?php foreach (array_values((array) ($bp['topics'] ?? [])) as $i => $rule): ?>
                                    <?= $ruleRow((string) $i, (int) ($rule['topic_id'] ?? 0), (int) ($rule['count'] ?? 1)) ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <template data-tr-template data-placeholder="__i__"><?= $ruleRow('__i__', null, 5) ?></template>
                        <?php if ($topics !== []): ?>
                            <button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm mt-2" data-tr-add><i class="fa-solid fa-plus" aria-hidden="true"></i> Regel</button>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3">
                        <span class="ignis-field__label">Feste Fragen, die immer drankommen</span>
                        <?= $multi('fixed[]', (array) ($bp['fixed'] ?? []), 'Feste Fragen') ?>
                    </div>
                </div>
                <div data-tr-variants-on<?= $useVariants ? '' : ' hidden' ?>>
                    <p class="ignis-detail__muted mt-0">Jeder Versuch bekommt einen dieser Bögen, ein Wiederholer möglichst einen anderen.</p>
                    <?php foreach (array_merge(array_values((array) ($bp['variants'] ?? [])), [[]]) as $v => $variant): ?>
                        <div class="mb-2">
                            <span class="ignis-field__label">Bogen <?= chr(65 + $v) ?><?= $variant === [] ? ' (neu)' : '' ?></span>
                            <?= $multi('variants[' . $v . '][]', (array) $variant, 'Bogen ' . chr(65 + $v)) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3">
                    <span class="ignis-field__label">Muss stimmen</span>
                    <?= $multi('knockouts[]', array_map('intval', $settings->knockouts), 'Fragen, die stimmen müssen') ?>
                    <p class="ignis-field__hint">Fehlt bei einer dieser Fragen die vollständig richtige Antwort, gilt der Teil als nicht bestanden, egal wie viele Punkte sonst erreicht sind.</p>
                </div>

                <h4>Ablauf</h4>
                <div class="tr-grid">
                    <?= $number('time_limit', 'Zeitlimit (Minuten)', $settings->attempts->timeLimitMinutes ?? '', $formId . '-time', 'Leer: ohne Limit', 'min="1"') ?>
                    <?= $number('pass_percent', 'Bestehensgrenze (%)', Plugin\Training\Exams::number($settings->passPercent), $formId . '-pass', '', 'min="1" max="100" step="0.5"') ?>
                    <?= $number('max_attempts', 'Versuche', $settings->attempts->maxAttempts, $formId . '-attempts', '', 'min="1"') ?>
                    <?= $number('cooldown_hours', 'Sperrfrist (Stunden)', $settings->attempts->cooldownHours, $formId . '-cooldown') ?>
                    <div>
                        <label for="<?= $formId ?>-review" class="ignis-field__label">Nach der Abgabe zeigen</label>
                        <select id="<?= $formId ?>-review" name="review" class="ignis-input" data-custom-dropdown="true">
                            <?php foreach (ReviewMode::cases() as $mode): ?>
                                <option value="<?= $mode->value ?>"<?= $settings->review === $mode ? ' selected' : '' ?>><?= htmlspecialchars($mode->label()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="<?= $formId ?>-release" class="ignis-field__label">Freigabe</label>
                        <select id="<?= $formId ?>-release" name="release" class="ignis-input" data-custom-dropdown="true">
                            <option value="lead"<?= $settings->requiresRelease ? ' selected' : '' ?>>Durch die Leitung</option>
                            <option value="auto"<?= !$settings->requiresRelease ? ' selected' : '' ?>>Sofort nach Zulassung</option>
                        </select>
                    </div>
                </div>
                <div class="flex gap-4 flex-wrap mt-3">
                    <?= $switch('shuffle_questions', (bool) ($bp['shuffle_questions'] ?? true), 'Fragen mischen') ?>
                    <?= $switch('shuffle_options', (bool) ($bp['shuffle_options'] ?? true), 'Antworten mischen') ?>
                    <label class="flex items-center gap-2"><span class="ignis-switch"><input type="checkbox" name="use_variants" value="1" data-tr-variants<?= $useVariants ? ' checked' : '' ?>><span></span></span> Feste Prüfungsbögen statt Zufall</label>
                    <?= $switch('grade_scale', $settings->gradeScale !== null, 'Note nach IHK-Schlüssel') ?>
                    <?= $switch('count_focus', (bool) ($s['count_focus'] ?? false), 'Verlassen des Fensters zählen') ?>
                </div>
            <?php else: ?>
                <h4>Fallbeispiele</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="md:col-span-2">
                        <label for="<?= $formId ?>-cases" class="ignis-sr-only">Fallbeispiele, eines je Zeile</label>
                        <textarea id="<?= $formId ?>-cases" name="cases" class="ignis-input" rows="3" placeholder="Ein Fallbeispiel je Zeile"><?= htmlspecialchars(implode("\n", $exam->cases())) ?></textarea>
                    </div>
                    <div class="grid gap-1 content-start">
                        <label class="ignis-radio"><input type="radio" name="case_mode" value="choose"<?= ($s['case_mode'] ?? 'choose') !== 'draw' ? ' checked' : '' ?>><span>Der Prüfer wählt</span></label>
                        <label class="ignis-radio"><input type="radio" name="case_mode" value="draw"<?= ($s['case_mode'] ?? '') === 'draw' ? ' checked' : '' ?>><span>Das System lost zu</span></label>
                    </div>
                </div>

                <h4>Kriterien</h4>
                <div data-tr-repeater>
                    <div data-tr-rows>
                        <?php foreach ($exam->sheet ?? [] as $g => $group): ?>
                            <?= $groupBlock((string) $g, (array) $group) ?>
                        <?php endforeach; ?>
                    </div>
                    <template data-tr-template data-placeholder="__i__"><?= $groupBlock('__i__', ['criteria' => [[]]]) ?></template>
                    <button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm mt-2" data-tr-add><i class="fa-solid fa-plus" aria-hidden="true"></i> Gruppe</button>
                </div>
                <p class="ignis-detail__muted">Ohne Punkte zählt ein Kriterium als erfüllt oder nicht erfüllt (ein Punkt). Ist „Muss erfüllt sein“ gesetzt, gilt der Praxisteil als nicht bestanden, wenn dieses Kriterium fehlt, unabhängig von den übrigen Punkten.</p>

                <h4>Bewertung</h4>
                <div class="tr-grid">
                    <?= $number('pass_percent', 'Bestehensgrenze (%)', Plugin\Training\Exams::number($settings->passPercent), $formId . '-pass', '', 'min="1" max="100" step="0.5"') ?>
                    <?= $number('max_attempts', 'Versuche', $settings->attempts->maxAttempts, $formId . '-attempts', '', 'min="1"') ?>
                    <?= $number('cooldown_hours', 'Sperrfrist (Stunden)', $settings->attempts->cooldownHours, $formId . '-cooldown') ?>
                    <div>
                        <label for="<?= $formId ?>-release" class="ignis-field__label">Freigabe</label>
                        <select id="<?= $formId ?>-release" name="release" class="ignis-input" data-custom-dropdown="true">
                            <option value="lead"<?= $settings->requiresRelease ? ' selected' : '' ?>>Durch die Leitung</option>
                            <option value="auto"<?= !$settings->requiresRelease ? ' selected' : '' ?>>Sofort nach Zulassung</option>
                        </select>
                    </div>
                </div>
                <div class="flex gap-4 flex-wrap mt-3">
                    <?= $switch('cosign', $settings->cosign, 'Zweiter Prüfer zeichnet gegen') ?>
                    <?= $switch('grade_scale', $settings->gradeScale !== null, 'Note nach IHK-Schlüssel') ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="tr-part__foot">
            <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-check" aria-hidden="true"></i> Speichern</button>
        </div>
    </form>
<?php endforeach; ?>
