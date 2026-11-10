<?php
/**
 * Laufende Theorieprüfung: eine Frage je Schritt (questionnaire.js),
 * rechts Übersicht und Restzeit. training.js speichert bei jedem Schritt
 * den ganzen Stand; die Frist prüft allein der Server.
 *
 * @var \Plugin\Training\Models\Attempt $attempt
 * @var \Plugin\Training\Models\Course  $course
 * @var \Plugin\Training\Models\Exam    $exam
 */

use EmergencyForge\Training\Question\QuestionType;
use Illuminate\Support\Carbon;
use Plugin\Training\Answers;
use Plugin\Training\Exams;

$layout     = 'admin';
$bodyId     = 'training';
$SITE_TITLE = $exam->title;
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$drawn      = $attempt->drawnExam();
$total      = count($drawn->questions);
$stored     = [];
foreach ($attempt->answers as $row) {
    $stored[$row->position] = $row->answer;
}
$iso = static fn (Carbon $time): string => $time->copy()->utc()->format('Y-m-d\TH:i:s\Z');
ob_start();
?>
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('plugins/training/assets/css/training.css')) ?>">
<?php $layoutHead = ob_get_clean(); ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <form method="post" action="<?= $base ?>training/attempts/<?= (int) $attempt->id ?>/submit"
                  data-ignis-questionnaire data-tr-exam
                  data-save-url="<?= $base ?>api/training/attempts/<?= (int) $attempt->id ?>/answers"
                  <?php if ($exam->setting('count_focus', false)): ?>data-focus-url="<?= $base ?>api/training/attempts/<?= (int) $attempt->id ?>/focus"<?php endif; ?>
                  <?php if ($attempt->deadline_at !== null): ?>data-deadline="<?= $iso($attempt->deadline_at) ?>" data-now="<?= $iso(Carbon::now()) ?>" data-warn="300"<?php endif; ?>>
                <?= csrf_field() ?>
                <div class="tr-exam__head">
                    <h1><?= htmlspecialchars($exam->title . ' · ' . $course->title) ?></h1>
                    <?php if ($attempt->deadline_at !== null): ?>
                        <span class="tr-timer"><i class="fa-regular fa-clock" aria-hidden="true"></i> <span data-ignis-remaining aria-label="Restzeit"></span></span>
                    <?php endif; ?>
                </div>
                <div class="tr-exam">
                    <div>
                        <?php foreach ($drawn->questions as $p => $question): ?>
                            <?php
                            $display = $question->forDisplay();
                            $kind    = $question->question->type;
                            $answer  = Answers::toForm($question, $stored[$p] ?? null);
                            $name    = 'answers[' . $p . ']';
                            $hint    = match ($kind) {
                                QuestionType::SingleChoice, QuestionType::TrueFalse => 'eine Antwort richtig',
                                QuestionType::MultipleChoice => 'eine oder mehrere Antworten richtig',
                                QuestionType::Matching       => 'jedem Begriff links das Passende zuordnen',
                                QuestionType::Ordering       => 'in die richtige Reihenfolge bringen',
                                default                      => null,
                            };
                            ?>
                            <fieldset data-ignis-step data-title="Frage <?= $p + 1 ?>" class="tr-qcard">
                                <legend class="tr-qcard__kicker">Frage <?= $p + 1 ?> von <?= $total ?> · <?= htmlspecialchars($kind->label()) ?> · <?= htmlspecialchars(Exams::number($question->question->points)) ?> <?= $question->question->points == 1 ? 'Punkt' : 'Punkte' ?><?= $hint !== null ? ' · ' . $hint : '' ?></legend>
                                <p class="tr-qcard__text"><?= nl2br(htmlspecialchars($question->question->body)) ?></p>
                                <?php if (!empty($display['image'])): ?>
                                    <img src="<?= $base ?>training/attempts/<?= (int) $attempt->id ?>/images/<?= (int) $p ?>" alt="" class="tr-qcard__image">
                                <?php endif; ?>

                                <?php if (in_array($kind, [QuestionType::SingleChoice, QuestionType::TrueFalse, QuestionType::MultipleChoice], true)): ?>
                                    <?php $multiple = $kind === QuestionType::MultipleChoice; ?>
                                    <div class="ignis-answers">
                                        <?php foreach ($display['options'] as $i => $text): ?>
                                            <?php $checked = $multiple ? in_array($i, (array) $answer, true) : $answer === $i; ?>
                                            <label class="ignis-answer">
                                                <input type="<?= $multiple ? 'checkbox' : 'radio' ?>" name="<?= $name . ($multiple ? '[]' : '') ?>" value="<?= (int) $i ?>"<?= $checked ? ' checked' : '' ?>>
                                                <span class="ignis-answer__text"><?= htmlspecialchars($text) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif ($kind === QuestionType::Matching): ?>
                                    <div class="tr-match">
                                        <?php foreach ($display['left'] as $l => $left): ?>
                                            <label for="q<?= $p ?>-<?= $l ?>"><?= htmlspecialchars($left) ?></label>
                                            <select id="q<?= $p ?>-<?= $l ?>" name="<?= $name ?>[<?= (int) $l ?>]" class="ignis-input" data-custom-dropdown="true">
                                                <option value="">Bitte wählen</option>
                                                <?php foreach ($display['right'] as $r => $right): ?>
                                                    <option value="<?= (int) $r ?>"<?= is_array($answer) && ($answer[$l] ?? null) === $r ? ' selected' : '' ?>><?= htmlspecialchars($right) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif ($kind === QuestionType::Ordering): ?>
                                    <?php
                                    $sequence = is_array($answer) && count($answer) === count($display['items']) ? $answer : array_keys($display['items']);
                                    $touched  = is_array($answer) && $answer !== [];
                                    ?>
                                    <ol class="tr-order" data-tr-order>
                                        <?php foreach ($sequence as $i): ?>
                                            <li>
                                                <input type="hidden" name="<?= $name ?>[]" value="<?= (int) $i ?>"<?= $touched ? '' : ' disabled' ?>>
                                                <span class="tr-order__text"><?= htmlspecialchars($display['items'][$i]) ?></span>
                                                <button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--icon ignis-btn--sm" data-tr-order-move="up" aria-label="Nach oben"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></button>
                                                <button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--icon ignis-btn--sm" data-tr-order-move="down" aria-label="Nach unten"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i></button>
                                            </li>
                                        <?php endforeach; ?>
                                    </ol>
                                    <p class="tr-meta">Gezählt wird die Reihenfolge, sobald du einen Schritt verschoben hast.</p>
                                <?php elseif ($kind === QuestionType::Number): ?>
                                    <div class="flex items-center gap-2 tr-narrow-field">
                                        <label for="q<?= $p ?>" class="ignis-sr-only">Antwort</label>
                                        <input type="text" inputmode="decimal" id="q<?= $p ?>" name="<?= $name ?>" class="ignis-input" autocomplete="off" value="<?= htmlspecialchars((string) $answer) ?>">
                                        <?php if (($display['unit'] ?? '') !== ''): ?><span><?= htmlspecialchars($display['unit']) ?></span><?php endif; ?>
                                    </div>
                                <?php elseif ($kind === QuestionType::ShortAnswer): ?>
                                    <label for="q<?= $p ?>" class="ignis-sr-only">Antwort</label>
                                    <input type="text" id="q<?= $p ?>" name="<?= $name ?>" class="ignis-input" maxlength="200" autocomplete="off" value="<?= htmlspecialchars((string) $answer) ?>">
                                <?php else: ?>
                                    <label for="q<?= $p ?>" class="ignis-sr-only">Antwort</label>
                                    <textarea id="q<?= $p ?>" name="<?= $name ?>" class="ignis-input" rows="8" maxlength="20000"><?= htmlspecialchars((string) $answer) ?></textarea>
                                <?php endif; ?>

                                <label class="flex items-center gap-2 mt-4 tr-flag"><span class="ignis-switch"><input type="checkbox" data-ignis-flag><span></span></span> Zum Nachsehen markieren</label>
                            </fieldset>
                        <?php endforeach; ?>
                        <div class="ignis-questionnaire__nav">
                            <button type="button" class="ignis-btn ignis-btn--secondary" data-ignis-back><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Zurück</button>
                            <button type="button" class="ignis-btn ignis-btn--primary" data-ignis-next>Weiter <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                            <button type="submit" class="ignis-btn ignis-btn--primary" data-ignis-finish><i class="fa-solid fa-check" aria-hidden="true"></i> Abgeben</button>
                        </div>
                    </div>
                    <aside class="tr-overview">
                        <b>Übersicht</b>
                        <nav data-ignis-overview aria-label="Fragen"></nav>
                        <p class="tr-meta mt-3 mb-3"><span data-ignis-answered></span> · <span data-tr-saved aria-live="polite">gespeichert</span></p>
                        <button type="submit" class="ignis-btn ignis-btn--secondary ignis-btn--block">Prüfung abgeben</button>
                    </aside>
                </div>
            </form>
        </div>
    </div>
    <script type="module" src="<?= $base ?>assets/js/ui/questionnaire.js"></script>
    <script type="module" src="<?= htmlspecialchars(asset('plugins/training/assets/js/training.js')) ?>"></script>
