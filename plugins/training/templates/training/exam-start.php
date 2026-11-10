<?php
/**
 * Startseite einer Theorieprüfung (bei Praxis nur der Stand).
 *
 * @var \Plugin\Training\Models\Course        $course
 * @var \Plugin\Training\Models\Participation $participation
 * @var \Plugin\Training\Models\Exam          $exam
 * @var \Plugin\Training\ExamState            $state
 */

use Plugin\Training\Exams;
use Plugin\Training\ExamState;
use Plugin\Training\Models\Attempt;

$layout     = 'admin';
$bodyId     = 'training';
$SITE_TITLE = $exam->title;
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$settings   = $exam->examSettings();
$bp         = $exam->blueprint ?? [];
$variants   = (array) ($bp['variants'] ?? []);
$count      = $variants !== []
    ? count((array) $variants[0])
    : array_sum(array_map(static fn ($r): int => (int) (((array) $r)['count'] ?? 0), (array) ($bp['topics'] ?? []))) + count((array) ($bp['fixed'] ?? []));
$used       = count($state->attempts);
$minutes    = $settings->attempts->timeLimitMinutes;
$blocker    = $state->blocker();
ob_start();
?>
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('plugins/training/assets/css/training.css')) ?>">
<?php $layoutHead = ob_get_clean(); ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= $base ?>training">Ausbildung</a></span> <span class="ignis-breadcrumb__item"><a href="<?= $base ?>training/courses/<?= (int) $course->id ?>"><?= htmlspecialchars($course->title) ?></a></span> <span class="ignis-breadcrumb__item" aria-current="page"><?= htmlspecialchars($exam->title) ?></span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <h1><?= htmlspecialchars($exam->title) ?></h1>
                    <p class="twplus-page-header__description"><?= htmlspecialchars($course->title . ' · ' . $course->type->name) ?></p>
                </div>
                <div class="header-actions twplus-page-header__actions">
                    <a href="<?= $base ?>training/courses/<?= (int) $course->id ?>" class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Zum Lehrgang</a>
                </div>
            </div>

            <div class="ignis-card tr-narrow"><div class="ignis-card__body">
                <dl class="ignis-detail__dl">
                    <?php if ($exam->isTheory()): ?>
                        <dt>Fragen</dt><dd><?= $count ?></dd>
                        <dt>Zeit</dt><dd><?= $minutes !== null ? $minutes . ' Minuten ab dem Start' : 'ohne Zeitlimit' ?></dd>
                    <?php else: ?>
                        <dt>Ablauf</dt><dd>Ein Prüfer bewertet dich vor Ort mit einem Bogen.</dd>
                    <?php endif; ?>
                    <dt>Bestanden ab</dt><dd><?= htmlspecialchars(Exams::number($settings->passPercent)) ?> % der Punkte<?= $settings->knockouts !== [] ? '; Fragen mit „Muss stimmen“ müssen vollständig richtig sein' : '' ?></dd>
                    <dt>Versuche</dt><dd><?= $used + $state->remaining ?><?= $used === 0 ? ', dies ist der erste' : ', ' . $used . ' davon genutzt' ?></dd>
                </dl>

                <?php if ($state->attempts !== []): ?>
                    <h2 class="ignis-card__title mt-4">Bisherige Versuche</h2>
                    <ul class="tr-exam-list">
                        <?php foreach ($state->attempts as $attempt): ?>
                            <li>
                                <div>
                                    <a href="<?= $base ?>training/attempts/<?= (int) $attempt->id ?>">Versuch <?= (int) $attempt->number ?></a>
                                    <div class="tr-meta"><?= htmlspecialchars($attempt->label()) ?><?= $attempt->status === Attempt::DONE ? ' · ' . htmlspecialchars(ExamState::score($exam, $attempt)) : '' ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($exam->isTheory() && $state->canStart()): ?>
                    <div class="ignis-alert ignis-alert--info mt-4"><div class="ignis-alert__body">Antworten werden bei jedem Schritt gespeichert. Ein Neuladen oder Absturz verliert nichts, die Uhr läuft aber weiter.<?= $exam->setting('count_focus', false) ? ' Wie oft du das Fenster verlässt, sieht der Prüfer.' : '' ?></div></div>
                    <form method="post" action="<?= $base ?>training/courses/<?= (int) $course->id ?>/exams/<?= (int) $exam->id ?>/start" class="flex gap-2 mt-4" onsubmit="<?= confirm_attr($minutes !== null ? 'Prüfung jetzt beginnen? Ab dann laufen ' . $minutes . ' Minuten.' : 'Prüfung jetzt beginnen?') ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="ignis-btn ignis-btn--primary ignis-btn--lg"><i class="fa-solid fa-play" aria-hidden="true"></i> Prüfung beginnen</button>
                    </form>
                <?php elseif ($blocker !== null): ?>
                    <div class="ignis-alert <?= $state->passed() ? 'ignis-alert--ok' : 'ignis-alert--info' ?> mt-4"><div class="ignis-alert__body"><?= htmlspecialchars($blocker) ?></div></div>
                <?php endif; ?>
            </div></div>
        </div>
    </div>
