<?php
/**
 * Ergebnis eines Versuchs für die Person selbst. Wie viel sie sieht, legt
 * die Prüfungsordnung fest (ReviewMode).
 *
 * @var \Plugin\Training\Models\Attempt                    $attempt
 * @var \Plugin\Training\Models\Course                     $course
 * @var \Plugin\Training\Models\Exam                       $exam
 * @var \EmergencyForge\Training\Exam\ExamResult|null      $result
 */

use EmergencyForge\Training\Exam\GradeScale;
use EmergencyForge\Training\Exam\ReviewMode;
use Plugin\Training\Exams;
use Plugin\Training\Models\Attempt;
use Plugin\Training\View;

$layout     = 'admin';
$bodyId     = 'training';
$SITE_TITLE = $exam->title;
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$settings   = $exam->examSettings();
$review     = $settings->review;
$done       = $attempt->status === Attempt::DONE && $result !== null;
$wrong      = [];
if ($done && $exam->isTheory() && $review !== ReviewMode::Result) {
    $drawn = $attempt->drawnExam();
    foreach ($attempt->answers as $row) {
        $question = $drawn->questions[$row->position]->question ?? null;
        if ($question !== null && $row->points() < $question->points) {
            $wrong[] = [$row, $question];
        }
    }
}
ob_start();
?>
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('plugins/training/assets/css/training.css')) ?>">
<?php $layoutHead = ob_get_clean(); ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= $base ?>training">Ausbildung</a></span> <span class="ignis-breadcrumb__item"><a href="<?= $base ?>training/courses/<?= (int) $course->id ?>"><?= htmlspecialchars($course->title) ?></a></span> <span class="ignis-breadcrumb__item" aria-current="page"><?= htmlspecialchars($exam->title) ?></span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <h1><?= htmlspecialchars($exam->title) ?><?= $exam->isTheory() ? ' abgegeben' : '' ?></h1>
                    <p class="twplus-page-header__description"><?= htmlspecialchars($course->title) ?> · Versuch <?= (int) $attempt->number ?><?= $attempt->submitted_at !== null ? ' · abgegeben am ' . htmlspecialchars($attempt->submitted_at->format('d.m.Y, H:i')) : '' ?></p>
                </div>
                <div class="header-actions twplus-page-header__actions">
                    <a href="<?= $base ?>training/courses/<?= (int) $course->id ?>" class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Zum Lehrgang</a>
                </div>
            </div>

            <?php if (!$done): ?>
                <div class="ignis-card tr-narrow"><div class="ignis-card__body">
                    <p class="m-0"><?= $attempt->status === Attempt::OPEN ? 'Der Prüfer füllt den Bewertungsbogen noch aus.' : htmlspecialchars($attempt->label()) . '. Du bekommst eine Nachricht, sobald das Ergebnis feststeht.' ?></p>
                </div></div>
            <?php else: ?>
                <?php $passed = $result->passed() === true; ?>
                <div class="ignis-card mb-4 tr-narrow"><div class="ignis-card__body tr-result">
                    <span class="tr-result__score"><?= htmlspecialchars(Attempt::percentLabel($result->percent())) ?></span>
                    <div class="tr-result__text">
                        <p class="m-0"><span class="ignis-chip <?= $passed ? 'ignis-chip--ok' : 'ignis-chip--danger' ?>"><?= $passed ? 'Bestanden' : 'Nicht bestanden' ?></span>
                            <?php if ($settings->gradeScale !== null): ?> <b>Note: <?= htmlspecialchars(GradeScale::label($settings->gradeScale->grade($result->percent()))) ?></b><?php endif; ?></p>
                        <p class="ignis-detail__muted mt-1 mb-0">
                            <?= htmlspecialchars(Exams::number($result->points)) ?> von <?= htmlspecialchars(Exams::number($result->maxPoints)) ?> Punkten · Grenze <?= htmlspecialchars(Exams::number($settings->passPercent)) ?> %
                            <?php if ($result->failedKnockouts !== []): ?> · <?= count($result->failedKnockouts) === 1 ? 'eine K.-o.-Frage falsch' : count($result->failedKnockouts) . ' K.-o.-Fragen falsch' ?><?php endif; ?>
                        </p>
                    </div>
                </div></div>

                <?php if ($exam->isTheory() && $review !== ReviewMode::Result): ?>
                    <div class="ignis-card tr-narrow"><div class="ignis-card__body">
                        <h2 class="ignis-card__title">Falsch oder nicht ganz richtig beantwortet</h2>
                        <?php if ($wrong === []): ?>
                            <p class="m-0">Alles richtig.</p>
                        <?php endif; ?>
                        <?php foreach ($wrong as [$row, $question]): ?>
                            <div class="tr-wrong">
                                <b><?= nl2br(htmlspecialchars($question->body)) ?></b>
                                <p class="tr-ans tr-ans--bad"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Deine Antwort: <?= htmlspecialchars(View::answerText($question, $row->answer)) ?> (<?= htmlspecialchars(Exams::number($row->points())) ?> von <?= htmlspecialchars(Exams::number($question->points)) ?>)</p>
                                <?php if ($review === ReviewMode::Solutions && ($solution = View::solutionText($question)) !== null): ?>
                                    <p class="tr-ans tr-ans--good"><i class="fa-solid fa-check" aria-hidden="true"></i> Richtig: <?= htmlspecialchars($solution) ?></p>
                                <?php endif; ?>
                                <?php if ($review === ReviewMode::Solutions && $question->explanation !== null && $question->explanation !== ''): ?>
                                    <p class="tr-meta"><?= nl2br(htmlspecialchars($question->explanation)) ?></p>
                                <?php endif; ?>
                                <?php if ($row->comment !== null && $row->comment !== ''): ?>
                                    <p class="tr-meta"><i class="fa-solid fa-comment" aria-hidden="true"></i> Prüfer: <?= htmlspecialchars($row->comment) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div></div>
                <?php endif; ?>
                <?php if (!$exam->isTheory() && ($comment = (string) (($attempt->drawn ?? [])['comment'] ?? '')) !== ''): ?>
                    <div class="ignis-card tr-narrow"><div class="ignis-card__body">
                        <h2 class="ignis-card__title">Kommentar des Prüfers</h2>
                        <p class="m-0"><?= nl2br(htmlspecialchars($comment)) ?></p>
                    </div></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
