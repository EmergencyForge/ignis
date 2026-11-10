<?php
/**
 * Frage anlegen oder bearbeiten. Je Fragart ein Fieldset; training.js
 * zeigt das gewählte und sperrt die anderen, damit nur dessen Felder
 * mitgehen.
 *
 * @var \Plugin\Training\Models\CourseType     $type
 * @var \Plugin\Training\Models\Question       $question
 * @var list<\Plugin\Training\Models\Topic>    $topics
 * @var int                                    $usage
 * @var bool                                   $versions  Speichern legt eine neue Version an
 */

use EmergencyForge\Training\Question\QuestionType;
use Plugin\Training\Exams;

$layout     = 'admin';
$bodyId     = 'training';
$isNew      = !$question->exists;
$SITE_TITLE = $isNew ? 'Frage anlegen' : 'Frage bearbeiten';
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$poolUrl    = $base . 'training/types/' . (int) $type->id . '#fragenpool';
$action     = $isNew ? $base . 'training/types/' . (int) $type->id . '/questions' : $base . 'training/questions/' . (int) $question->id;
$kind       = $question->questionType();
$spec       = $question->spec ?? [];
$specOf     = static fn (QuestionType $k): array => $k === $kind ? $spec : [];

$remove = '<button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--icon ignis-btn--sm" data-tr-remove aria-label="Entfernen" data-ignis-tooltip="Entfernen"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>';
$choiceRow = static fn (string $i, string $text, bool $right, bool $multiple): string => '<div class="tr-answer" data-tr-row>'
    . '<input type="text" name="options[' . $i . ']" class="ignis-input" maxlength="500" placeholder="Antwort" aria-label="Antwort" value="' . htmlspecialchars($text) . '">'
    . '<label class="' . ($multiple ? 'ignis-checkbox' : 'ignis-radio') . '"><input type="' . ($multiple ? 'checkbox' : 'radio') . '" name="' . ($multiple ? 'correct[]' : 'correct') . '" value="' . $i . '"' . ($right ? ' checked' : '') . '><span>richtig</span></label>'
    . $remove . '</div>';
$pairRow = static fn (string $i, string $left, string $right): string => '<div class="tr-answer" data-tr-row>'
    . '<input type="text" name="pairs[' . $i . '][0]" class="ignis-input" maxlength="300" placeholder="Links" aria-label="Links" value="' . htmlspecialchars($left) . '">'
    . '<i class="fa-solid fa-arrow-right tr-meta" aria-hidden="true"></i>'
    . '<input type="text" name="pairs[' . $i . '][1]" class="ignis-input" maxlength="300" placeholder="Rechts" aria-label="Rechts" value="' . htmlspecialchars($right) . '">'
    . $remove . '</div>';
$itemRow = static fn (string $i, string $text): string => '<div class="tr-answer" data-tr-row>'
    . '<span class="tr-meta tr-answer__pos" aria-hidden="true"></span>'
    . '<input type="text" name="items[' . $i . ']" class="ignis-input" maxlength="300" placeholder="Schritt" aria-label="Schritt" value="' . htmlspecialchars($text) . '">'
    . $remove . '</div>';
$rows = static function (array $values, int $min, callable $row): string {
    $html = '';
    foreach (array_values($values) as $i => $value) {
        $html .= $row((string) $i, $value);
    }
    for ($i = count($values); $i < $min; $i++) {
        $html .= $row((string) $i, null);
    }

    return $html;
};
$choice = static function (QuestionType $k) use ($specOf, $choiceRow): string {
    $s       = $specOf($k);
    $multi   = $k === QuestionType::MultipleChoice;
    $correct = array_map('intval', (array) ($s['correct'] ?? []));
    $options = (array) ($s['options'] ?? []);
    $html    = '';
    foreach (array_values($options) as $i => $text) {
        $html .= $choiceRow((string) $i, (string) $text, in_array($i, $correct, true), $multi);
    }
    for ($i = count($options); $i < 4; $i++) {
        $html .= $choiceRow((string) $i, '', false, $multi);
    }

    return $html;
};
ob_start();
?>
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('plugins/training/assets/css/training.css')) ?>">
<?php $layoutHead = ob_get_clean(); ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= $base ?>training/types/<?= (int) $type->id ?>"><?= htmlspecialchars($type->name) ?></a></span> <span class="ignis-breadcrumb__item"><a href="<?= $poolUrl ?>">Fragenpool</a></span> <span class="ignis-breadcrumb__item" aria-current="page"><?= $isNew ? 'Neue Frage' : 'Frage ' . (int) $question->id ?></span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <h1><?= $SITE_TITLE ?></h1>
                    <p class="twplus-page-header__description"><?= htmlspecialchars($type->name) ?> · Fragenpool<?= $isNew ? '' : ' · Version ' . (int) $question->version ?></p>
                </div>
                <div class="header-actions twplus-page-header__actions">
                    <a href="<?= $poolUrl ?>" class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Zum Fragenpool</a>
                </div>
            </div>

            <?php if ($versions): ?>
                <div class="ignis-alert ignis-alert--warn mb-4"><div class="ignis-alert__body">Diese Frage kam in <?= $usage ?> <?= $usage === 1 ? 'Versuch' : 'Versuchen' ?> vor. Wer Text, Antworten oder Punkte ändert, legt Version <?= (int) $question->version + 1 ?> an; abgegebene Versuche behalten den alten Wortlaut und die alte Lösung.</div></div>
            <?php endif; ?>

            <form method="post" action="<?= htmlspecialchars($action) ?>" enctype="multipart/form-data" data-tr-question-form>
                <?= csrf_field() ?>
                <input type="hidden" name="type" value="<?= $kind->value ?>" data-tr-question-type>
                <div class="ignis-detail">
                    <div class="ignis-detail__main">
                        <div class="ignis-card mb-4"><div class="ignis-card__body grid gap-4">
                            <div>
                                <span class="ignis-field__label" id="question-kind-label">Art</span>
                                <div class="ignis-segmented ignis-segmented--sm tr-kinds" role="group" aria-labelledby="question-kind-label">
                                    <?php foreach (QuestionType::cases() as $option): ?>
                                        <button type="button" data-tr-kind="<?= $option->value ?>" aria-pressed="<?= $option === $kind ? 'true' : 'false' ?>"><?= htmlspecialchars($option->label()) ?></button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div>
                                <label for="question-body" class="ignis-field__label">Fragetext <span class="ignis-field__required">*</span></label>
                                <textarea id="question-body" name="body" class="ignis-input" rows="3" required><?= htmlspecialchars($question->body) ?></textarea>
                            </div>

                            <fieldset class="tr-spec" data-tr-spec="single_choice">
                                <legend class="ignis-field__label">Antworten, genau eine richtig</legend>
                                <div data-tr-repeater>
                                    <div data-tr-rows><?= $choice(QuestionType::SingleChoice) ?></div>
                                    <template data-tr-template data-placeholder="__i__"><?= $choiceRow('__i__', '', false, false) ?></template>
                                    <button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm mt-2" data-tr-add><i class="fa-solid fa-plus" aria-hidden="true"></i> Antwort</button>
                                </div>
                            </fieldset>
                            <fieldset class="tr-spec" data-tr-spec="multiple_choice">
                                <legend class="ignis-field__label">Antworten, eine oder mehrere richtig</legend>
                                <div data-tr-repeater>
                                    <div data-tr-rows><?= $choice(QuestionType::MultipleChoice) ?></div>
                                    <template data-tr-template data-placeholder="__i__"><?= $choiceRow('__i__', '', false, true) ?></template>
                                    <button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm mt-2" data-tr-add><i class="fa-solid fa-plus" aria-hidden="true"></i> Antwort</button>
                                </div>
                                <div class="grid gap-1 mt-3">
                                    <span class="ignis-field__label">Bewertung</span>
                                    <label class="ignis-radio"><input type="radio" name="partial" value="0"<?= empty($specOf(QuestionType::MultipleChoice)['partial']) ? ' checked' : '' ?>><span>Alles oder nichts</span></label>
                                    <label class="ignis-radio"><input type="radio" name="partial" value="1"<?= !empty($specOf(QuestionType::MultipleChoice)['partial']) ? ' checked' : '' ?>><span>Anteilig, falsche Kreuze ziehen ab</span></label>
                                </div>
                            </fieldset>
                            <fieldset class="tr-spec" data-tr-spec="true_false">
                                <legend class="ignis-field__label">Die Aussage ist</legend>
                                <?php $tfFalse = (int) (((array) ($specOf(QuestionType::TrueFalse)['correct'] ?? []))[0] ?? 0) === 1; ?>
                                <div class="flex gap-4">
                                    <label class="ignis-radio"><input type="radio" name="statement" value="true"<?= !$tfFalse ? ' checked' : '' ?>><span>Wahr</span></label>
                                    <label class="ignis-radio"><input type="radio" name="statement" value="false"<?= $tfFalse ? ' checked' : '' ?>><span>Falsch</span></label>
                                </div>
                            </fieldset>
                            <fieldset class="tr-spec" data-tr-spec="matching">
                                <legend class="ignis-field__label">Paare; die rechte Seite wird gemischt</legend>
                                <div data-tr-repeater>
                                    <div data-tr-rows><?= $rows((array) ($specOf(QuestionType::Matching)['pairs'] ?? []), 3, static fn (string $i, mixed $p): string => $pairRow($i, (string) (((array) $p)[0] ?? ''), (string) (((array) $p)[1] ?? ''))) ?></div>
                                    <template data-tr-template data-placeholder="__i__"><?= $pairRow('__i__', '', '') ?></template>
                                    <button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm mt-2" data-tr-add><i class="fa-solid fa-plus" aria-hidden="true"></i> Paar</button>
                                </div>
                            </fieldset>
                            <fieldset class="tr-spec" data-tr-spec="ordering">
                                <legend class="ignis-field__label">Schritte in der richtigen Reihenfolge; angezeigt werden sie gemischt</legend>
                                <div data-tr-repeater>
                                    <div data-tr-rows class="tr-ordered"><?= $rows((array) ($specOf(QuestionType::Ordering)['items'] ?? []), 3, static fn (string $i, mixed $t): string => $itemRow($i, (string) $t)) ?></div>
                                    <template data-tr-template data-placeholder="__i__"><?= $itemRow('__i__', '') ?></template>
                                    <button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm mt-2" data-tr-add><i class="fa-solid fa-plus" aria-hidden="true"></i> Schritt</button>
                                </div>
                                <label class="flex items-center gap-2 mt-3"><span class="ignis-switch"><input type="checkbox" name="per_position" value="1"<?= !empty($specOf(QuestionType::Ordering)['per_position']) ? ' checked' : '' ?>><span></span></span> Punkte je richtiger Position statt alles oder nichts</label>
                            </fieldset>
                            <fieldset class="tr-spec" data-tr-spec="number">
                                <legend class="ignis-field__label">Richtige Zahl</legend>
                                <?php $ns = $specOf(QuestionType::Number); ?>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <div><label for="q-value" class="ignis-field__label">Wert</label><input type="text" inputmode="decimal" id="q-value" name="value" class="ignis-input" value="<?= isset($ns['value']) ? htmlspecialchars(Exams::number((float) $ns['value'])) : '' ?>"></div>
                                    <div><label for="q-tolerance" class="ignis-field__label">Toleranz (±)</label><input type="text" inputmode="decimal" id="q-tolerance" name="tolerance" class="ignis-input" value="<?= isset($ns['tolerance']) ? htmlspecialchars(Exams::number((float) $ns['tolerance'])) : '' ?>"></div>
                                    <div><label for="q-unit" class="ignis-field__label">Einheit</label><input type="text" id="q-unit" name="unit" class="ignis-input" maxlength="20" placeholder="z. B. m" value="<?= htmlspecialchars((string) ($ns['unit'] ?? '')) ?>"></div>
                                </div>
                            </fieldset>
                            <fieldset class="tr-spec" data-tr-spec="short_answer">
                                <legend class="ignis-field__label">Akzeptierte Antworten, eine je Zeile</legend>
                                <?php $ss = $specOf(QuestionType::ShortAnswer); ?>
                                <textarea name="accepted" class="ignis-input" rows="3" aria-label="Akzeptierte Antworten"><?= htmlspecialchars(implode("\n", (array) ($ss['accepted'] ?? []))) ?></textarea>
                                <label class="flex items-center gap-2 mt-3"><span class="ignis-switch"><input type="checkbox" name="tolerant" value="1"<?= ($ss['tolerant'] ?? true) ? ' checked' : '' ?>><span></span></span> Kleine Tippfehler zulassen</label>
                                <p class="ignis-field__hint">Was keiner Antwort entspricht, landet beim Prüfer.</p>
                            </fieldset>
                            <fieldset class="tr-spec" data-tr-spec="free_text">
                                <legend class="ignis-field__label">Freitext</legend>
                                <p class="m-0">Die Antwort bewertet ein Prüfer. Schreib in die Erklärung, was eine gute Antwort enthält; Prüfer sehen sie beim Bewerten.</p>
                            </fieldset>
                        </div></div>

                        <div class="ignis-card"><div class="ignis-card__body grid gap-4">
                            <div>
                                <label for="question-explanation" class="ignis-field__label">Erklärung</label>
                                <textarea id="question-explanation" name="explanation" class="ignis-input" rows="2"><?= htmlspecialchars((string) $question->explanation) ?></textarea>
                                <p class="ignis-field__hint">Erscheint je nach Prüfungsordnung nach der Abgabe.</p>
                            </div>
                            <div>
                                <span class="ignis-field__label">Bild</span>
                                <?php if ($question->image_path !== null && $question->exists): ?>
                                    <div class="flex items-center gap-3 mb-2">
                                        <img src="<?= $base ?>training/questions/<?= (int) $question->id ?>/image" alt="" class="tr-question-image">
                                        <label class="ignis-checkbox"><input type="checkbox" name="remove_image" value="1"><span>Bild entfernen</span></label>
                                    </div>
                                <?php endif; ?>
                                <div class="ignis-file ignis-file--dropzone" data-ignis-file>
                                    <input type="file" id="question-image" name="image" class="ignis-file__input" accept=".png,.jpg,.jpeg,.webp">
                                    <label for="question-image" class="ignis-file__zone">
                                        <span class="ignis-file__icon" aria-hidden="true"><i class="fa-solid fa-image"></i></span>
                                        <span class="ignis-file__title">Bild hierher ziehen oder <span class="ignis-file__link">auswählen</span></span>
                                        <span class="ignis-file__hint">PNG, JPG oder WebP</span>
                                    </label>
                                    <div class="ignis-file__selected" hidden></div>
                                </div>
                            </div>
                        </div></div>
                    </div>
                    <aside class="ignis-detail__aside">
                        <div class="ignis-detail__block grid gap-3">
                            <div>
                                <label for="question-topic" class="ignis-field__label">Thema</label>
                                <select id="question-topic" name="topic_id" class="ignis-input" data-custom-dropdown="true">
                                    <option value="">Ohne Thema</option>
                                    <?php foreach ($topics as $topic): ?>
                                        <option value="<?= (int) $topic->id ?>"<?= $question->topic_id === $topic->id ? ' selected' : '' ?>><?= htmlspecialchars($topic->name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="question-points" class="ignis-field__label">Punkte</label>
                                <input type="number" id="question-points" name="points" class="ignis-input" min="0.5" step="0.5" value="<?= htmlspecialchars(Exams::number($question->points)) ?>">
                            </div>
                            <label class="flex items-center gap-2"><span class="ignis-switch"><input type="checkbox" name="is_active" value="1"<?= $question->is_active ? ' checked' : '' ?>><span></span></span> Aktiv</label>
                        </div>
                        <div class="ignis-detail__block grid gap-2">
                            <button type="submit" class="ignis-btn ignis-btn--primary ignis-btn--block"><i class="fa-solid fa-check" aria-hidden="true"></i> Speichern</button>
                            <button type="submit" name="next" value="new" class="ignis-btn ignis-btn--secondary ignis-btn--block">Speichern und nächste Frage</button>
                            <?php if (!$isNew): ?>
                                <p class="ignis-detail__muted mb-0">Gestellt in <?= $usage ?> <?= $usage === 1 ? 'Versuch' : 'Versuchen' ?>.</p>
                                <button type="submit" form="question-delete" class="ignis-btn ignis-btn--ghost-danger ignis-btn--block"><i class="fa-solid fa-trash" aria-hidden="true"></i> Löschen</button>
                            <?php endif; ?>
                        </div>
                    </aside>
                </div>
            </form>
            <?php if (!$isNew): ?>
                <form method="post" action="<?= $base ?>training/questions/<?= (int) $question->id ?>/delete" id="question-delete" hidden onsubmit="<?= confirm_attr('Frage löschen? Abgegebene Versuche zeigen sie weiter.') ?>">
                    <?= csrf_field() ?>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <script type="module" src="<?= htmlspecialchars(asset('plugins/training/assets/js/training.js')) ?>"></script>
