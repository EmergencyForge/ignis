<?php
/**
 * Lehrgang aus Sicht eines Teilnehmers: Stand mit „Weiter bei“, Module mit
 * Einheiten, rechts die Termine. Vor Beginn nur der Lehrplan ohne Links,
 * dazu Anmelden oder Zurückziehen.
 *
 * @var \Plugin\Training\Models\Course                   $course
 * @var \Plugin\Training\Models\Participation|null       $participation
 * @var \Plugin\Training\Curriculum                      $curriculum
 * @var array<int, array{state: string, manual: bool, by: ?int, pages: ?array<string, mixed>, attendance: ?string}> $state
 * @var \EmergencyForge\Training\Progress\Progress       $progress
 * @var \Plugin\Training\Models\Unit|null                $nextUnit
 * @var string|null                                      $resume
 * @var bool                                             $learning
 * @var bool                                             $instructor
 * @var list<string>                                     $missing
 * @var bool                                             $linked
 */

use EmergencyForge\Training\CourseStatus;
use EmergencyForge\Training\Progress\ProgressUnit;
use EmergencyForge\Training\UnitKind;
use Plugin\Training\Models\Attendance;
use Plugin\Training\View;

$layout     = 'admin';
$bodyId     = 'training';
$SITE_TITLE = $course->title;
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$open       = $learning || $instructor;
$lead       = $course->lead !== null ? ($course->lead->fullname ?: $course->lead->username) : null;
ob_start();
?>
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('plugins/training/assets/css/training.css')) ?>">
<?php $layoutHead = ob_get_clean(); ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= $base ?>training">Ausbildung</a></span> <span class="ignis-breadcrumb__item" aria-current="page"><?= htmlspecialchars($course->title) ?></span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <h1><?= htmlspecialchars($course->title) ?> <span class="ignis-chip <?= View::courseChip($course->state()) ?>"><?= htmlspecialchars($course->state()->label()) ?></span></h1>
                    <p class="twplus-page-header__description"><?= htmlspecialchars(implode(' · ', array_filter([$course->type->name, $course->period(), $lead !== null ? 'Leitung ' . $lead : null]))) ?></p>
                </div>
                <?php if ($instructor): ?>
                    <div class="header-actions twplus-page-header__actions">
                        <a href="<?= $base ?>training/manage/<?= (int) $course->id ?>" class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i> Lehrgang leiten</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="ignis-detail">
                <div class="ignis-detail__main">
                    <?php if ($learning): ?>
                        <div class="ignis-card mb-4">
                            <div class="ignis-card__body tr-resume">
                                <div class="tr-resume__text">
                                    <div class="ignis-progress ignis-progress--lg" role="progressbar" aria-valuenow="<?= $progress->percent() ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Fortschritt"><div class="ignis-progress__bar" style="width: <?= $progress->percent() ?>%"></div></div>
                                    <p class="mt-2 mb-0"><b><?= $progress->percent() ?> %</b> der Pflicht-Einheiten erledigt (<?= $progress->requiredDone() ?> von <?= $progress->requiredTotal() ?>)</p>
                                    <?php if ($resume !== null): ?>
                                        <p class="ignis-detail__muted m-0">Weiter bei: <?= htmlspecialchars($resume) ?></p>
                                    <?php else: ?>
                                        <p class="ignis-detail__muted m-0">Alles erledigt.</p>
                                    <?php endif; ?>
                                </div>
                                <?php if ($nextUnit !== null): ?>
                                    <a href="<?= $base ?>training/courses/<?= (int) $course->id ?>/units/<?= (int) $nextUnit->id ?>" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-play" aria-hidden="true"></i> Weiter lernen</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php elseif ($participation !== null): ?>
                        <div class="ignis-card mb-4">
                            <div class="ignis-card__body tr-resume">
                                <div class="tr-resume__text">
                                    <p class="m-0"><span class="ignis-chip <?= View::participationChip($participation->state()) ?>"><?= htmlspecialchars($participation->state()->label()) ?></span>
                                        <?php if ($participation->state()->value === 'waitlisted'): ?>
                                            Platz <?= (int) $participation->waitlist_position ?> auf der Warteliste. Wird ein Platz frei, rückst du nach.
                                        <?php elseif ($participation->state()->value === 'enrolled'): ?>
                                            Die Leitung bestätigt deine Anmeldung noch.
                                        <?php elseif ($participation->state()->value === 'admitted'): ?>
                                            Die Unterlagen öffnen, sobald der Lehrgang beginnt.
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <?php if (in_array($participation->state()->value, ['waitlisted', 'enrolled', 'admitted'], true) && !$course->state()->materialsOpen()): ?>
                                    <form method="post" action="<?= $base ?>training/courses/<?= (int) $course->id ?>/withdraw" onsubmit="<?= confirm_attr('Anmeldung zu ' . $course->title . ' zurückziehen?') ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="ignis-btn ignis-btn--secondary">Anmeldung zurückziehen</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php elseif ($course->state() === CourseStatus::EnrollmentOpen && !$instructor): ?>
                        <div class="ignis-card mb-4">
                            <div class="ignis-card__body tr-resume">
                                <div class="tr-resume__text">
                                    <p class="m-0"><?= htmlspecialchars($course->type->enrollment()->label()) ?><?= $course->enroll_until !== null ? ', bis ' . htmlspecialchars($course->enroll_until->format('d.m.Y, H:i')) : '' ?>.</p>
                                    <?php if ($missing !== []): ?>
                                        <p class="ignis-detail__muted m-0">Setzt <?= htmlspecialchars(implode(' und ', $missing)) ?> voraus.</p>
                                    <?php endif; ?>
                                </div>
                                <?php if ($linked && $missing === [] && !$course->enrollmentClosed() && $course->type->enrollment()->allowsSelfEnrollment()): ?>
                                    <form method="post" action="<?= $base ?>training/courses/<?= (int) $course->id ?>/enroll">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Anmelden</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($instructor && !$learning): ?>
                        <p class="ignis-detail__muted mb-3">Vorschau als Ausbilder: die Einheiten lassen sich öffnen, Fortschritt wird nicht gezählt.</p>
                    <?php endif; ?>

                    <?php if ($course->type->description): ?>
                        <p class="mb-4"><?= nl2br(htmlspecialchars((string) $course->type->description)) ?></p>
                    <?php endif; ?>

                    <?php foreach ($curriculum->modules as $index => $module):
                        $units    = array_values(array_filter($module->units->all(), static fn ($u): bool => $u->type() !== UnitKind::Check));
                        $required = array_filter($units, static fn ($u): bool => $u->required);
                        $doneCount = count(array_filter($required, static fn ($u): bool => ($state[$u->id]['state'] ?? '') === 'done'));
                        $first    = $units[0] ?? null;
                        $locked   = $learning && $first !== null && !$progress->isUnlocked(new ProgressUnit($first->id, $first->module_id, $first->required));
                        $moduleDone = $required !== [] && $doneCount === count($required);
                    ?>
                        <div class="tr-module">
                            <div class="tr-module__head">
                                <?php if ($learning): ?>
                                    <?php if ($locked): ?>
                                        <span class="tr-state tr-state--lock"><i class="fa-solid fa-lock" aria-hidden="true"></i></span>
                                    <?php elseif ($moduleDone): ?>
                                        <span class="tr-state tr-state--done"><i class="fa-solid fa-check" aria-hidden="true"></i></span>
                                    <?php else: ?>
                                        <span class="tr-state <?= $doneCount > 0 ? 'tr-state--part' : 'tr-state--open' ?>"></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <h3>Modul <?= $index + 1 ?> · <?= htmlspecialchars($module->title) ?></h3>
                                <span class="ignis-detail__muted"><?= $locked ? 'Erst nach Modul ' . $index : ($learning ? $doneCount . ' von ' . count($required) : count($units) . ' Einheiten') ?></span>
                            </div>
                            <?php if ($units === []): ?>
                                <div class="tr-module__empty">Noch keine Einheiten.</div>
                            <?php endif; ?>
                            <?php foreach ($units as $unit):
                                $s    = $state[$unit->id] ?? null;
                                $tag  = $open && !$locked ? 'a' : 'div';
                                $href = $tag === 'a' ? ' href="' . $base . 'training/courses/' . (int) $course->id . '/units/' . (int) $unit->id . '"' : '';
                            ?>
                                <<?= $tag ?> class="tr-unit<?= $locked ? ' is-locked' : '' ?>"<?= $href ?>>
                                    <span class="tr-unit__icon"><i class="<?= htmlspecialchars($unit->icon()) ?>" aria-hidden="true"></i></span>
                                    <div class="tr-unit__title">
                                        <b><?= htmlspecialchars($unit->title) ?></b>
                                        <?php if (!$unit->required): ?> <span class="ignis-chip ignis-chip--sm ignis-chip--secondary">Freiwillig</span><?php endif; ?>
                                        <div class="tr-unit__meta"><?= htmlspecialchars(View::unitMeta($unit, $s, $curriculum->session($unit))) ?></div>
                                    </div>
                                    <?php if ($learning): ?>
                                        <?php if ($locked): ?>
                                            <span class="tr-state tr-state--lock" aria-label="Gesperrt"><i class="fa-solid fa-lock" aria-hidden="true"></i></span>
                                        <?php elseif (($s['state'] ?? '') === 'done'): ?>
                                            <span class="tr-state tr-state--done" aria-label="Erledigt"><i class="fa-solid fa-check" aria-hidden="true"></i></span>
                                        <?php elseif ($s !== null): ?>
                                            <span class="tr-state tr-state--part" aria-label="Begonnen"></span>
                                        <?php else: ?>
                                            <span class="tr-state tr-state--open" aria-label="Offen"></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </<?= $tag ?>>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($curriculum->modules === []): ?>
                        <div class="ignis-card"><div class="ignis-card__body"><p class="ignis-detail__muted m-0">Der Lehrplan ist noch leer.</p></div></div>
                    <?php endif; ?>
                </div>

                <aside class="ignis-detail__aside">
                    <div class="ignis-detail__block">
                        <h2 class="ignis-card__title">Termine</h2>
                        <?php /** @var list<\Plugin\Training\Models\Session> $sessions */ $sessions = $course->sessions()->get()->all(); ?>
                        <?php if ($sessions === []): ?>
                            <p class="ignis-detail__muted mb-0">Noch keine Termine.</p>
                        <?php else: ?>
                            <div class="ignis-timeline mt-2">
                                <?php foreach ($sessions as $session):
                                    $attended = $session->unit_id !== null ? ($state[$session->unit_id]['attendance'] ?? null) : null;
                                    if ($attended === null && $participation !== null) {
                                        $attended = Attendance::query()->where('session_id', $session->id)->where('participation_id', $participation->id)->value('state');
                                    }
                                ?>
                                    <div class="ignis-timeline__item">
                                        <div class="ignis-timeline__when"><?= htmlspecialchars($session->when()) ?></div>
                                        <div class="ignis-timeline__text">
                                            <?= htmlspecialchars($session->title) ?>
                                            <?php if ($attended === 'present'): ?>
                                                <span class="ignis-chip ignis-chip--sm ignis-chip--ok">Anwesend</span>
                                            <?php elseif ($attended !== null): ?>
                                                <span class="ignis-chip ignis-chip--sm ignis-chip--secondary"><?= htmlspecialchars(Attendance::STATES[$attended] ?? '') ?></span>
                                            <?php elseif ($session->required): ?>
                                                <span class="ignis-chip ignis-chip--sm ignis-chip--warn">Pflicht</span>
                                            <?php endif; ?>
                                            <?php if ($session->location): ?><div class="ignis-detail__muted"><?= htmlspecialchars($session->location) ?></div><?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="ignis-detail__block">
                        <h2 class="ignis-card__title">Prüfungen</h2>
                        <p class="ignis-detail__muted mb-0">Theorie und Praxis öffnen in der Prüfungsphase, wenn alle Pflicht-Einheiten erledigt sind.</p>
                    </div>
                </aside>
            </div>
        </div>
    </div>
