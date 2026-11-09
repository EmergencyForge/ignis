<?php
/**
 * Reiter Unterlagen: der Lehrplan der Lehrgangsart (nur lesen, gilt live)
 * und die Zusatz-Module dieses Lehrgangs, die die Leitung selbst pflegt.
 *
 * @var \Plugin\Training\Models\Course $course
 * @var \Plugin\Training\Curriculum    $curriculum
 * @var bool                           $canLead
 * @var string                         $manageUrl
 */

use Plugin\Training\Policies\TrainingPolicy;

$base        = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$typeModules = array_values(array_filter($curriculum->modules, static fn ($m): bool => $m->course_type_id !== null));
$ownModules  = array_values(array_filter($curriculum->modules, static fn ($m): bool => $m->course_id !== null));
?>
<div class="flex items-center justify-between gap-3 mb-3 flex-wrap">
    <p class="ignis-detail__muted m-0">Der Lehrplan kommt aus der Lehrgangsart „<?= htmlspecialchars($course->type->name) ?>“. Änderungen dort gelten sofort auch hier, der Fortschritt bleibt.</p>
    <?php if (TrainingPolicy::manage()): ?>
        <a href="<?= $base ?>training/types/<?= (int) $course->course_type_id ?>#lehrplan" class="ignis-btn ignis-btn--secondary ignis-btn--sm"><i class="fa-solid fa-pen" aria-hidden="true"></i> Lehrplan bearbeiten</a>
    <?php endif; ?>
</div>

<?php foreach ($typeModules as $i => $module): ?>
    <div class="tr-module">
        <div class="tr-module__head"><h3>Modul <?= $i + 1 ?> · <?= htmlspecialchars($module->title) ?></h3></div>
        <?php foreach ($curriculum->units() as $unit): if ($unit->module_id !== $module->id) { continue; } ?>
            <a class="tr-unit" href="<?= $base ?>training/courses/<?= (int) $course->id ?>/units/<?= (int) $unit->id ?>">
                <span class="tr-unit__icon"><i class="<?= htmlspecialchars($unit->icon()) ?>" aria-hidden="true"></i></span>
                <div class="tr-unit__title">
                    <b><?= htmlspecialchars($unit->title) ?></b>
                    <?php if (!$unit->required): ?> <span class="ignis-chip ignis-chip--sm ignis-chip--secondary">Freiwillig</span><?php endif; ?>
                    <div class="tr-unit__meta"><?= htmlspecialchars(\Plugin\Training\View::unitMeta($unit, null, $curriculum->session($unit))) ?></div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

<h3 class="ignis-card__title mt-4 mb-2">Eigene Module dieses Lehrgangs</h3>
<?php if ($canLead && !$course->state()->isFinal()): ?>
    <?php
    $editModules  = $ownModules;
    $moduleOffset = count($typeModules);
    $addModuleUrl = $manageUrl . '/modules';
    require dirname(__DIR__) . '/partials/curriculum-edit.php';
    ?>
<?php elseif ($ownModules === []): ?>
    <p class="ignis-detail__muted">Keine.</p>
<?php else: ?>
    <?php foreach ($ownModules as $i => $module): ?>
        <div class="tr-module"><div class="tr-module__head"><h3>Modul <?= count($typeModules) + $i + 1 ?> · <?= htmlspecialchars($module->title) ?></h3></div></div>
    <?php endforeach; ?>
<?php endif; ?>
