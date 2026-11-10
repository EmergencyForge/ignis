<?php
/**
 * Lehrgangsart. Allgemein und Lehrplan mit `training.manage`, Fragenpool
 * und Prüfungsordnung mit `training.exams`, Abschluss wieder mit
 * `training.manage`.
 *
 * @var \Plugin\Training\Models\CourseType $type
 * @var array<string, mixed>               $form
 * @var array{targets: array<string, string>, qualifications: array<string, array<string, string>>} $options
 * @var int                                $courses
 */

use Plugin\Training\Models\CourseType;
use Plugin\Training\Policies\TrainingPolicy;
use Plugin\Training\Qualifications;

$layout       = 'admin';
$bodyId       = 'training';
$windowSingle = true;
$SITE_TITLE   = $type->name;
$base         = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$typeUrl      = $base . 'training/types/' . (int) $type->id;
$canManage    = TrainingPolicy::manage();
$canExams     = TrainingPolicy::exams();
$prereqs      = array_map(static fn (string $k): string => Qualifications::short($k), $type->prerequisiteKeys());
$description  = array_filter([
    CourseType::AREAS[$type->area] ?? null,
    $type->enrollment()->label(),
    $prereqs !== [] ? 'setzt ' . implode(' und ', $prereqs) . ' voraus' : null,
]);
ob_start();
?>
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('plugins/training/assets/css/training.css')) ?>">
<?php $layoutHead = ob_get_clean(); ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= $base ?>training/types">Lehrgangsarten</a></span> <span class="ignis-breadcrumb__item" aria-current="page"><?= htmlspecialchars($type->name) ?></span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <h1><?= htmlspecialchars($type->name) ?> (<?= htmlspecialchars($type->short) ?>)
                        <?php if ($type->is_active): ?><span class="ignis-chip ignis-chip--ok">Aktiv</span><?php else: ?><span class="ignis-chip ignis-chip--secondary">Inaktiv</span><?php endif; ?>
                    </h1>
                    <p class="twplus-page-header__description"><?= htmlspecialchars(implode(' · ', $description)) ?></p>
                </div>
                <?php if ($canManage): ?>
                    <div class="header-actions twplus-page-header__actions">
                        <?php if ($canExams): ?>
                            <a href="<?= $typeUrl ?>/export" class="ignis-btn ignis-btn--ghost" data-ignis-tooltip="Lehrplan, Fragenpool und Prüfungsordnung als JSON, zum Austausch zwischen Servern"><i class="fa-solid fa-file-export" aria-hidden="true"></i> Exportieren</a>
                        <?php endif; ?>
                        <form method="post" action="<?= $typeUrl ?>/copy" class="inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-copy" aria-hidden="true"></i> Kopieren</button>
                        </form>
                        <?php if ($type->is_active): ?>
                            <a href="<?= $base ?>training/manage/create?type=<?= (int) $type->id ?>" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Lehrgang anlegen</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="ignis-tabs" data-ignis-tabs data-default="<?= $canManage ? 'lehrplan' : 'fragenpool' ?>">
                <div class="ignis-tabs__headers" role="tablist">
                    <?php if ($canManage): ?>
                        <button type="button" class="ignis-tabs__header" data-tab="allgemein">Allgemein</button>
                        <button type="button" class="ignis-tabs__header" data-tab="lehrplan">Lehrplan</button>
                    <?php endif; ?>
                    <?php if ($canExams): ?>
                        <button type="button" class="ignis-tabs__header" data-tab="fragenpool">Fragenpool <span class="ignis-tabs__count"><?= count($questions) ?></span></button>
                        <button type="button" class="ignis-tabs__header" data-tab="pruefungsordnung">Prüfungsordnung</button>
                    <?php endif; ?>
                    <?php if ($canManage): ?>
                        <button type="button" class="ignis-tabs__header" data-tab="abschluss">Abschluss</button>
                    <?php endif; ?>
                </div>
                <div class="ignis-tabs__panels">
                    <?php if ($canManage): ?>
                        <section class="ignis-tabs__panel" data-panel="allgemein">
                            <form method="post" action="<?= $typeUrl ?>" class="ignis-card">
                                <?= csrf_field() ?>
                                <div class="ignis-card__body">
                                    <?php require __DIR__ . '/_general.php'; ?>
                                </div>
                                <div class="ignis-card__footer" data-form-actions>
                                    <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-check" aria-hidden="true"></i> Speichern</button>
                                </div>
                            </form>
                            <?php if ($courses === 0): ?>
                                <form method="post" action="<?= $typeUrl ?>/delete" class="mt-3" onsubmit="<?= confirm_attr('Lehrgangsart ' . $type->name . ' mit Lehrplan löschen?') ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="ignis-btn ignis-btn--ghost-danger"><i class="fa-solid fa-trash" aria-hidden="true"></i> Lehrgangsart löschen</button>
                                </form>
                            <?php else: ?>
                                <p class="ignis-detail__muted mt-3">Es gibt <?= $courses ?> Lehrgänge dieser Art. Statt zu löschen, auf inaktiv setzen.</p>
                            <?php endif; ?>
                        </section>
                        <section class="ignis-tabs__panel" data-panel="lehrplan">
                            <div class="flex items-center justify-between gap-3 mb-3 flex-wrap">
                                <label class="flex items-center gap-2">
                                    <span class="ignis-switch"><input type="checkbox" data-training-sequential="<?= $typeUrl ?>/sequential"<?= $type->sequential ? ' checked' : '' ?>><span></span></span>
                                    Der Reihe nach: ein Modul öffnet erst, wenn das vorige erledigt ist
                                </label>
                            </div>
                            <?php
                            $editModules  = $type->modules->all();
                            $moduleOffset = 0;
                            $addModuleUrl = $typeUrl . '/modules';
                            require dirname(__DIR__) . '/partials/curriculum-edit.php';
                            ?>
                            <p class="ignis-detail__muted mt-3">Module und Einheiten lassen sich ziehen. Lehrgänge dieser Art nutzen diesen Lehrplan, auch laufende, und können eigene Module ergänzen.</p>
                        </section>
                    <?php endif; ?>
                    <?php if ($canExams): ?>
                        <section class="ignis-tabs__panel" data-panel="fragenpool">
                            <?php require __DIR__ . '/_pool.php'; ?>
                        </section>
                        <section class="ignis-tabs__panel" data-panel="pruefungsordnung">
                            <?php require __DIR__ . '/_order.php'; ?>
                        </section>
                    <?php endif; ?>
                    <?php if ($canManage): ?>
                        <section class="ignis-tabs__panel" data-panel="abschluss">
                            <?php require __DIR__ . '/_completion.php'; ?>
                        </section>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php if ($canExams): ?><script type="module" src="<?= $base ?>assets/js/ui/multi-select.js"></script><?php endif; ?>
    <script type="module" src="<?= $base ?>assets/js/ui/tabs.js"></script>
    <script type="module" src="<?= $base ?>assets/js/ui/menu.js"></script>
    <script type="module" src="<?= htmlspecialchars(asset('plugins/training/assets/js/training.js')) ?>"></script>
