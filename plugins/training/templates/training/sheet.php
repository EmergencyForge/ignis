<?php
/**
 * Praxisbogen: große Knöpfe und Zähler, damit er auf dem Tablet bedienbar
 * ist. Die Leiste unten rechnet training.js live mit; zählt wird beim
 * Speichern auf dem Server.
 *
 * @var \Plugin\Training\Models\Attempt                  $attempt
 * @var \Plugin\Training\Models\Course                   $course
 * @var \Plugin\Training\Models\Exam                     $exam
 * @var \EmergencyForge\Training\Exam\PracticalSheet     $sheet
 * @var array<string, float|bool|null>                   $ratings
 * @var \EmergencyForge\Training\Exam\ExamResult         $result
 * @var bool                                             $mayRate
 * @var bool                                             $mayCosign
 */

use Plugin\Training\Exams;
use Plugin\Training\Models\Attempt;

$layout     = 'admin';
$bodyId     = 'training';
$person     = $attempt->participation->name();
$SITE_TITLE = $exam->title . ': ' . $person;
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$drawn      = $attempt->drawn ?? [];
$comments   = (array) ($drawn['comments'] ?? []);
$case       = $drawn['case'] ?? null;
$disabled   = $mayRate ? '' : ' disabled';
ob_start();
?>
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('plugins/training/assets/css/training.css')) ?>">
<?php $layoutHead = ob_get_clean(); ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page tr-sheet">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= $base ?>training/manage">Lehrgänge</a></span> <span class="ignis-breadcrumb__item"><a href="<?= $base ?>training/manage/<?= (int) $course->id ?>#pruefungen"><?= htmlspecialchars($course->title) ?></a></span> <span class="ignis-breadcrumb__item" aria-current="page"><?= htmlspecialchars($person) ?></span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <h1><?= htmlspecialchars($exam->title) ?></h1>
                    <p class="twplus-page-header__description">
                        <?= htmlspecialchars($person . ' · ' . $course->title . ' · Versuch ' . $attempt->number) ?>
                        <?php if ($case !== null): ?> · Fallbeispiel <b><?= htmlspecialchars((string) $case) ?></b><?= !empty($drawn['case_drawn']) ? ' (zugelost)' : '' ?><?php endif; ?>
                    </p>
                </div>
            </div>

            <?php if (!$attempt->isOpen()): ?>
                <div class="ignis-alert <?= $attempt->status === Attempt::DONE ? ($attempt->passed ? 'ignis-alert--ok' : 'ignis-alert--danger') : 'ignis-alert--info' ?> mb-4"><div class="ignis-alert__body">
                    <?= htmlspecialchars($attempt->label()) ?>: <?= htmlspecialchars(Exams::number($result->points)) ?> von <?= htmlspecialchars(Exams::number($result->maxPoints)) ?> Punkten (<?= htmlspecialchars(Attempt::percentLabel($result->percent())) ?>)<?= $attempt->status === Attempt::DONE ? ', ' . ($attempt->passed ? 'bestanden' : 'nicht bestanden') : '' ?>.
                </div></div>
            <?php endif; ?>

            <form method="post" action="<?= $base ?>training/attempts/<?= (int) $attempt->id ?>/sheet" data-tr-sheet>
                <?= csrf_field() ?>
                <?php foreach ($sheet->groups as $g => $group): ?>
                    <section class="tr-sheet__group">
                        <h2><?= htmlspecialchars($group['title'] !== '' ? $group['title'] : 'Kriterien') ?> <span data-tr-group-sum></span></h2>
                        <?php foreach ($group['criteria'] as $criterion): ?>
                            <?php
                            $key    = $criterion['key'];
                            $value  = $ratings[$key] ?? null;
                            $max    = $criterion['points'];
                            $field  = 'ratings[' . htmlspecialchars($key) . ']';
                            $label  = 'crit-' . htmlspecialchars($key);
                            ?>
                            <div class="tr-sheet__row" data-tr-criterion data-max="<?= htmlspecialchars((string) ($max ?? 1)) ?>">
                                <b id="<?= $label ?>"><?= htmlspecialchars($criterion['label']) ?><?php if ($criterion['knockout']): ?> <span class="ignis-chip ignis-chip--sm ignis-chip--danger">muss erfüllt sein</span><?php endif; ?></b>
                                <?php if ($max === null): ?>
                                    <span class="tr-big" role="radiogroup" aria-labelledby="<?= $label ?>">
                                        <label class="is-yes"><input type="radio" name="<?= $field ?>" value="yes"<?= $value === true ? ' checked' : '' ?><?= $disabled ?>><span><i class="fa-solid fa-check" aria-hidden="true"></i> erfüllt</span></label>
                                        <label class="is-no"><input type="radio" name="<?= $field ?>" value="no"<?= $value === false ? ' checked' : '' ?><?= $disabled ?>><span><i class="fa-solid fa-xmark" aria-hidden="true"></i> nicht erfüllt</span></label>
                                    </span>
                                <?php else: ?>
                                    <span class="tr-step">
                                        <button type="button" data-tr-step="-1" aria-label="Weniger"<?= $disabled ?>>−</button>
                                        <input type="text" inputmode="decimal" name="<?= $field ?>" aria-labelledby="<?= $label ?>" placeholder="offen" value="<?= $value !== null && !is_bool($value) ? htmlspecialchars(Exams::number((float) $value)) : '' ?>"<?= $disabled ?>>
                                        <span>/ <?= htmlspecialchars(Exams::number((float) $max)) ?></span>
                                        <button type="button" data-tr-step="1" aria-label="Mehr"<?= $disabled ?>>+</button>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <div class="tr-sheet__row">
                            <label for="group-comment-<?= $g ?>" class="ignis-sr-only">Kommentar zur Gruppe</label>
                            <input type="text" id="group-comment-<?= $g ?>" name="comments[<?= $g ?>]" class="ignis-input" maxlength="2000" placeholder="Kommentar zur Gruppe" value="<?= htmlspecialchars((string) ($comments[$g] ?? '')) ?>"<?= $disabled ?>>
                        </div>
                    </section>
                <?php endforeach; ?>
                <div class="ignis-card mb-4"><div class="ignis-card__body">
                    <label for="sheet-comment" class="ignis-field__label">Kommentar für den Teilnehmer</label>
                    <textarea id="sheet-comment" name="comment" class="ignis-input" rows="3" maxlength="4000"<?= $disabled ?>><?= htmlspecialchars((string) ($drawn['comment'] ?? '')) ?></textarea>
                </div></div>

                <?php if ($mayRate): ?>
                    <div class="tr-sticky">
                        <span class="tr-sticky__sum" data-tr-sheet-sum aria-live="polite"></span>
                        <button type="submit" class="ignis-btn ignis-btn--secondary ignis-btn--lg">Speichern</button>
                        <button type="submit" name="finish" value="1" class="ignis-btn ignis-btn--primary ignis-btn--lg" onclick="<?= confirm_attr($exam->examSettings()->cosign ? 'Bewertung abschließen? Danach zeichnet ein zweiter Prüfer gegen.' : 'Bewertung abschließen? Das Ergebnis geht an den Teilnehmer.') ?>">Bewertung abschließen</button>
                    </div>
                <?php endif; ?>
            </form>
            <?php if ($mayCosign): ?>
                <form method="post" action="<?= $base ?>training/attempts/<?= (int) $attempt->id ?>/cosign" class="tr-sticky" onsubmit="<?= confirm_attr('Bogen gegenzeichnen? Das Ergebnis geht an den Teilnehmer.') ?>">
                    <?= csrf_field() ?>
                    <span class="tr-sticky__sum">Bewertet, wartet auf deine Gegenzeichnung.</span>
                    <button type="submit" class="ignis-btn ignis-btn--primary ignis-btn--lg"><i class="fa-solid fa-signature" aria-hidden="true"></i> Gegenzeichnen</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <script type="module" src="<?= htmlspecialchars(asset('plugins/training/assets/js/training.js')) ?>"></script>
