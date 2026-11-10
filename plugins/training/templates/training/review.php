<?php
/**
 * Theorieversuch bewerten oder ansehen. Fragen, die ein Prüfer bewerten
 * muss, zuerst; automatisch bewertete lassen sich mit Begründung ändern.
 *
 * @var \Plugin\Training\Models\Attempt                    $attempt
 * @var \Plugin\Training\Models\Course                     $course
 * @var \Plugin\Training\Models\Exam                       $exam
 * @var \EmergencyForge\Training\Exam\ExamResult|null      $result
 * @var bool                                               $mayGrade
 */

use EmergencyForge\Training\Question\QuestionType;
use Plugin\Training\Exams;
use Plugin\Training\Models\Attempt;
use Plugin\Training\View;

$layout     = 'admin';
$bodyId     = 'training';
$person     = $attempt->participation->name();
$SITE_TITLE = 'Bewerten: ' . $person;
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$drawn      = $attempt->drawnExam();
$knockouts  = array_map('intval', $exam->examSettings()->knockouts);
$rows       = $attempt->answers->sortBy(static fn ($r): array => [$r->needs_review ? 0 : 1, $r->position])->all();
$auto       = 0.0;
$autoMax    = 0.0;
$manual     = 0.0;
$manualMax  = 0.0;
foreach ($rows as $row) {
    $max = $drawn->questions[$row->position]->question->points ?? 0.0;
    if ($row->needs_review) {
        $manual    += (float) ($row->manual_points ?? 0);
        $manualMax += $max;
    } else {
        $auto    += $row->points();
        $autoMax += $max;
    }
}
$meta = array_filter([
    $course->title,
    $exam->title,
    'Versuch ' . $attempt->number,
    $attempt->submitted_at !== null ? 'abgegeben ' . $attempt->submitted_at->format('d.m., H:i') : null,
    $attempt->focus_lost > 0 ? 'Fenster ' . $attempt->focus_lost . '-mal verlassen' : null,
]);
ob_start();
?>
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('plugins/training/assets/css/training.css')) ?>">
<?php $layoutHead = ob_get_clean(); ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= $base ?>training/manage">Lehrgänge</a></span> <span class="ignis-breadcrumb__item"><a href="<?= $base ?>training/manage/<?= (int) $course->id ?>#bewertungen"><?= htmlspecialchars($course->title) ?></a></span> <span class="ignis-breadcrumb__item" aria-current="page"><?= htmlspecialchars($person) ?></span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <h1><?= $mayGrade ? 'Bewerten: ' : '' ?><?= htmlspecialchars($person) ?></h1>
                    <p class="twplus-page-header__description"><?= htmlspecialchars(implode(' · ', $meta)) ?></p>
                </div>
                <div class="header-actions twplus-page-header__actions">
                    <a href="<?= $base ?>training/manage/<?= (int) $course->id ?>#pruefungen" class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Zum Lehrgang</a>
                </div>
            </div>

            <?php if ($attempt->isOpen()): ?>
                <div class="ignis-alert ignis-alert--info"><div class="ignis-alert__body">Der Versuch läuft noch<?= $attempt->deadline_at !== null ? ' bis ' . htmlspecialchars($attempt->deadline_at->format('H:i')) . ' Uhr' : '' ?>.</div></div>
            <?php else: ?>
            <form method="post" action="<?= $base ?>training/attempts/<?= (int) $attempt->id ?>/review">
                <?= csrf_field() ?>
                <div class="ignis-detail">
                    <div class="ignis-detail__main">
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $question = $drawn->questions[$row->position]->question ?? null;
                            if ($question === null) {
                                continue;
                            }
                            $p        = (int) $row->position;
                            $open     = $row->needs_review && $row->manual_points === null;
                            $kicker   = array_filter([
                                'Frage ' . ($p + 1),
                                $question->type->label(),
                                Exams::number($question->points) . ($question->points == 1 ? ' Punkt' : ' Punkte'),
                                in_array($question->id, $knockouts, true) ? 'K.-o.' : null,
                                $row->needs_review && $question->type === QuestionType::ShortAnswer ? 'automatisch nicht erkannt' : null,
                            ]);
                            $solution = View::solutionText($question);
                            ?>
                            <div class="ignis-card mb-4<?= $open ? ' tr-card--open' : '' ?>"><div class="ignis-card__body">
                                <div class="tr-qcard__kicker"><?= htmlspecialchars(implode(' · ', $kicker)) ?></div>
                                <p class="tr-review__q"><?= nl2br(htmlspecialchars($question->body)) ?></p>
                                <?php if ($question->type === QuestionType::FreeText): ?>
                                    <div class="tr-freetext"><?= $row->answer !== null && $row->answer !== '' ? nl2br(htmlspecialchars((string) $row->answer)) : '<span class="tr-meta">keine Antwort</span>' ?></div>
                                    <?php if ($question->explanation): ?><p class="tr-meta mt-2">Erwartet: <?= htmlspecialchars($question->explanation) ?></p><?php endif; ?>
                                <?php else: ?>
                                    <p class="mt-2 mb-1">Antwort: <b><?= htmlspecialchars(View::answerText($question, $row->answer)) ?></b></p>
                                    <?php if ($solution !== null): ?><p class="tr-meta mb-0">Richtig: <?= htmlspecialchars($solution) ?></p><?php endif; ?>
                                <?php endif; ?>

                                <?php if ($row->needs_review): ?>
                                    <div class="flex gap-3 items-end flex-wrap mt-4">
                                        <div class="tr-points">
                                            <label for="points-<?= $p ?>" class="ignis-field__label">Punkte (von <?= htmlspecialchars(Exams::number($question->points)) ?>)</label>
                                            <input type="text" inputmode="decimal" id="points-<?= $p ?>" name="points[<?= $p ?>]" class="ignis-input" value="<?= $row->manual_points !== null ? htmlspecialchars(Exams::number((float) $row->manual_points)) : '' ?>"<?= $mayGrade ? '' : ' disabled' ?>>
                                        </div>
                                        <div class="flex-1 tr-comment">
                                            <label for="comment-<?= $p ?>" class="ignis-field__label">Kommentar für den Teilnehmer</label>
                                            <input type="text" id="comment-<?= $p ?>" name="comments[<?= $p ?>]" class="ignis-input" maxlength="2000" value="<?= htmlspecialchars((string) $row->comment) ?>"<?= $mayGrade ? '' : ' disabled' ?>>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="tr-meta mt-2 mb-0">Automatisch: <?= htmlspecialchars(Exams::number((float) $row->auto_points)) ?> <?= (float) $row->auto_points == 1.0 ? 'Punkt' : 'Punkte' ?><?= $row->isOverridden() ? ', geändert auf ' . htmlspecialchars(Exams::number((float) $row->manual_points)) : '' ?><?= $row->comment ? ' · ' . htmlspecialchars($row->comment) : '' ?></p>
                                    <?php if ($mayGrade): ?>
                                        <details class="mt-2"<?= $row->isOverridden() ? ' open' : '' ?>>
                                            <summary class="tr-meta">Punkte ändern</summary>
                                            <div class="flex gap-3 items-end flex-wrap mt-2">
                                                <div class="tr-points">
                                                    <label for="points-<?= $p ?>" class="ignis-field__label">Punkte</label>
                                                    <input type="text" inputmode="decimal" id="points-<?= $p ?>" name="points[<?= $p ?>]" class="ignis-input" value="<?= $row->isOverridden() ? htmlspecialchars(Exams::number((float) $row->manual_points)) : '' ?>">
                                                </div>
                                                <div class="flex-1 tr-comment">
                                                    <label for="comment-<?= $p ?>" class="ignis-field__label">Begründung</label>
                                                    <input type="text" id="comment-<?= $p ?>" name="comments[<?= $p ?>]" class="ignis-input" maxlength="2000" value="<?= htmlspecialchars((string) $row->comment) ?>">
                                                </div>
                                            </div>
                                        </details>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div></div>
                        <?php endforeach; ?>
                    </div>
                    <aside class="ignis-detail__aside">
                        <div class="ignis-detail__block">
                            <h2 class="ignis-card__title">Stand</h2>
                            <dl class="ignis-detail__dl">
                                <dt>Automatisch</dt><dd><?= htmlspecialchars(Exams::number($auto)) ?> von <?= htmlspecialchars(Exams::number($autoMax)) ?> Punkten</dd>
                                <?php if ($manualMax > 0): ?><dt>Von Hand</dt><dd><?= htmlspecialchars(Exams::number($manual)) ?> von <?= htmlspecialchars(Exams::number($manualMax)) ?> Punkten</dd><?php endif; ?>
                                <?php if ($result !== null): ?>
                                    <dt>Zusammen</dt><dd><b><?= htmlspecialchars(Exams::number($result->points)) ?> von <?= htmlspecialchars(Exams::number($result->maxPoints)) ?> (<?= htmlspecialchars(Attempt::percentLabel($result->percent())) ?>)</b><?= $result->pending > 0 ? ', ' . $result->pending . ' offen' : '' ?></dd>
                                <?php endif; ?>
                                <dt>Status</dt><dd><?= htmlspecialchars($attempt->label()) ?></dd>
                            </dl>
                            <?php if ($mayGrade): ?>
                                <button type="submit" name="finish" value="1" class="ignis-btn ignis-btn--primary ignis-btn--block mt-3">Bewertung abschließen</button>
                                <button type="submit" class="ignis-btn ignis-btn--secondary ignis-btn--block mt-2">Zwischenstand speichern</button>
                                <p class="ignis-detail__muted mt-2 mb-0">Automatische Punkte lassen sich nur mit Begründung ändern.</p>
                            <?php endif; ?>
                        </div>
                    </aside>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
