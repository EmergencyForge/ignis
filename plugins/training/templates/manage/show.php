<?php
/**
 * Lehrgang für Leitung, Ausbilder und Prüfer: Teilnehmer, Fortschritt,
 * Termine, Unterlagen, Prüfungen, Bewertungen und Abschluss.
 *
 * @var \Plugin\Training\Models\Course                    $course
 * @var list<\Plugin\Training\Models\Participation>       $participations
 * @var list<\Plugin\Training\Models\Participation>       $learners
 * @var \Plugin\Training\Curriculum                       $curriculum
 * @var array<int, array<int, array{state: string, manual: bool, by: ?int, pages: ?array<string, mixed>, attendance: ?string}>> $states
 * @var array<int, int>                                   $percent
 * @var int                                               $average
 * @var list<\Plugin\Training\Models\Session>             $sessions
 * @var array<int, array<int, string>>                    $attendance
 * @var array<int, array<int, string>>                    $responses
 * @var list<\App\Models\Personnel>                       $personnel
 * @var array<int, string>                                $names
 * @var list<\Plugin\Training\Models\Unit>                $openSlots
 * @var int                                               $seated
 * @var int                                               $waiting
 * @var bool                                              $canLead
 * @var bool                                              $calendar
 * @var \Plugin\Training\ExamBoard                        $board
 * @var bool                                              $canExamine
 * @var list<\Plugin\Training\Models\Attempt>             $reviews
 * @var array<int, \Plugin\Training\Models\ExamRelease>   $releases
 * @var array<int, array{completion: \EmergencyForge\Training\Completion\Completion, document_id: ?int}> $completions
 */

use EmergencyForge\Training\CourseStatus;
use Plugin\Training\Qualifications;
use Plugin\Training\View;

$layout       = 'admin';
$bodyId       = 'training';
$windowSingle = true;
$SITE_TITLE   = $course->title;
$base         = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$manageUrl    = $base . 'training/manage/' . (int) $course->id;
$status       = $course->state();
$next         = match ($status) {
    CourseStatus::Planned        => CourseStatus::EnrollmentOpen,
    CourseStatus::EnrollmentOpen => CourseStatus::Running,
    CourseStatus::Running        => CourseStatus::ExamPhase,
    CourseStatus::ExamPhase      => CourseStatus::Completed,
    default                      => null,
};
$description = [];
$description[] = $seated . ($course->seats !== null ? ' von ' . $course->seats . ' Plätzen belegt' : ' Teilnehmer');
if ($waiting > 0) {
    $description[] = $waiting . ' auf der Warteliste';
}
$headline = implode(', ', $description) . ($learners !== [] ? ' · Ø ' . $average . ' % erledigt' : '');
$startQual = Qualifications::label($course->type->start_qualification);
$open      = array_map(static fn ($p): string => $p->name(), array_values(array_filter($learners, static fn ($p): bool => in_array($p->status, ['in_training', 'in_exam'], true))));
$closeText = 'Lehrgang abschließen?';
if ($open !== [] && array_filter($board->parts, static fn ($e): bool => (bool) $e->required) === []) {
    $closeText = 'Lehrgang abschließen? Ohne Pflichtprüfung haben damit alle ' . count($open) . ' in Ausbildung bestanden.';
} elseif ($open !== []) {
    $last      = array_pop($open);
    $who       = $open === [] ? $last : implode(', ', $open) . ' und ' . $last;
    $closeText = (count($open) + 1) . ' Teilnehmer haben noch nicht bestanden: ' . $who . '. Sie gelten danach als nicht bestanden und bekommen eine Nachricht. Das lässt sich nicht zurücknehmen.';
}
ob_start();
?>
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('plugins/training/assets/css/training.css')) ?>">
<?php $layoutHead = ob_get_clean(); ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= $base ?>training">Ausbildung</a></span> <span class="ignis-breadcrumb__item"><a href="<?= $base ?>training/manage">Lehrgänge</a></span> <span class="ignis-breadcrumb__item" aria-current="page"><?= htmlspecialchars($course->title) ?></span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow"><?= htmlspecialchars($course->type->name) ?> · <?= htmlspecialchars($course->period()) ?></p>
                    <h1><?= htmlspecialchars($course->title) ?> <span class="ignis-chip <?= View::courseChip($status) ?>"><?= htmlspecialchars($status->label()) ?></span></h1>
                    <p class="twplus-page-header__description"><?= htmlspecialchars($headline) ?></p>
                </div>
                <div class="header-actions twplus-page-header__actions">
                    <a href="<?= $base ?>training/courses/<?= (int) $course->id ?>" class="ignis-btn ignis-btn--ghost"><i class="fa-solid fa-eye" aria-hidden="true"></i> Teilnehmersicht</a>
                    <?php if ($canLead): ?>
                        <a href="<?= $manageUrl ?>/edit" class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-pen" aria-hidden="true"></i> Bearbeiten</a>
                        <?php if ($next !== null): ?>
                            <form method="post" action="<?= $manageUrl ?>/status" class="inline" onsubmit="<?= confirm_attr(match (true) {
                                $next === CourseStatus::Running && $startQual !== null => 'Lehrgang starten? Alle Zugelassenen sind dann in Ausbildung und bekommen ' . $startQual . '.',
                                $next === CourseStatus::Completed => $closeText,
                                default => 'Status auf „' . $next->label() . '“ setzen?',
                            }) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="status" value="<?= htmlspecialchars($next->value) ?>">
                                <?php if ($next === CourseStatus::Completed): ?>
                                    <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-flag-checkered" aria-hidden="true"></i> Lehrgang abschließen</button>
                                <?php else: ?>
                                    <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-forward" aria-hidden="true"></i> Status: <?= htmlspecialchars($next->label()) ?></button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            $tabs = [
                ['id' => 'teilnehmer', 'label' => 'Teilnehmer', 'count' => count(array_filter($participations, static fn ($p): bool => !$p->state()->isFinal() || in_array($p->status, ['passed', 'failed'], true))), 'partial' => '_participants'],
                ['id' => 'fortschritt', 'label' => 'Fortschritt', 'partial' => '_progress'],
                ['id' => 'termine', 'label' => 'Termine', 'count' => count($sessions), 'partial' => '_sessions'],
                ['id' => 'unterlagen', 'label' => 'Unterlagen', 'partial' => '_materials'],
            ];
            if ($board->parts !== []) {
                $tabs[] = ['id' => 'pruefungen', 'label' => 'Prüfungen', 'partial' => '_exams'];
            }
            if ($completions !== [] || $status === CourseStatus::ExamPhase || $status === CourseStatus::Completed) {
                $tabs[] = ['id' => 'abschluss', 'label' => 'Abschluss', 'partial' => '_completion'];
            }
            if ($canExamine) {
                $tabs[] = ['id' => 'bewertungen', 'label' => 'Bewertungen', 'count' => count($reviews), 'partial' => '_reviews'];
            }
            ?>
            <div class="ignis-tabs" data-ignis-tabs data-default="<?= $status->materialsOpen() ? 'fortschritt' : 'teilnehmer' ?>">
                <div class="ignis-tabs__headers" role="tablist">
                    <?php foreach ($tabs as $tab): ?>
                        <button type="button" class="ignis-tabs__header" data-tab="<?= $tab['id'] ?>"><?= $tab['label'] ?><?php if (isset($tab['count'])): ?> <span class="ignis-tabs__count"><?= (int) $tab['count'] ?></span><?php endif; ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="ignis-tabs__panels">
                    <?php foreach ($tabs as $tab): ?>
                        <section class="ignis-tabs__panel" data-panel="<?= $tab['id'] ?>">
                            <?php require __DIR__ . '/' . $tab['partial'] . '.php'; ?>
                        </section>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($canLead && !$status->isFinal()): ?>
                <details class="mt-6">
                    <summary class="ignis-detail__muted">Lehrgang absagen</summary>
                    <form method="post" action="<?= $manageUrl ?>/status" class="ignis-card mt-2" onsubmit="<?= confirm_attr('Lehrgang ' . $course->title . ' absagen? Alle offenen Teilnahmen werden beendet.') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" value="cancelled">
                        <div class="ignis-card__body grid gap-3">
                            <p class="m-0">Beendet alle Anmeldungen und Teilnahmen. Termine verschwinden aus dem Kalender.</p>
                            <?php if ($startQual !== null && $status->materialsOpen()): ?>
                                <label class="flex items-center gap-2"><input type="checkbox" name="reset_qualification" value="1"> <?= htmlspecialchars($startQual) ?> bei allen zurücksetzen, die sie durch diesen Lehrgang bekommen haben</label>
                            <?php endif; ?>
                            <div><button type="submit" class="ignis-btn ignis-btn--danger">Lehrgang absagen</button></div>
                        </div>
                    </form>
                </details>
            <?php endif; ?>
        </div>
    </div>
    <script type="module" src="<?= $base ?>assets/js/ui/tabs.js"></script>
    <script type="module" src="<?= $base ?>assets/js/ui/menu.js"></script>
    <script type="module" src="<?= htmlspecialchars(asset('plugins/training/assets/js/training.js')) ?>"></script>
