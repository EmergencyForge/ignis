<?php
/**
 * Profilkarte „Ausbildung“: die Lehrgänge des Mitarbeiters mit Status.
 * Sichtbar für alle, die das Profil sehen und Lehrgänge leiten oder
 * verwalten; das Profil selbst prüft die Rechte für den Rest. Wem eine
 * Folgeaufgabe zu diesem Mitarbeiter gehört, sieht sie hier auch ohne
 * dieses Recht und hakt sie hier ab.
 *
 * @var \App\Models\Personnel $mitarbeiter
 */

use Plugin\Training\FollowUps;
use Plugin\Training\Models\Participation;
use Plugin\Training\Policies\TrainingPolicy;
use Plugin\Training\View;

$trainingTeach  = TrainingPolicy::teach();
$trainingManage = TrainingPolicy::manage();
$trainingUser   = (int) ($_SESSION['userid'] ?? 0);
$trainingTasks  = array_values(array_filter(FollowUps::openAbout((int) $mitarbeiter->id), static fn (object $t): bool => $trainingManage || FollowUps::owns($t, $trainingUser)));
if (!$trainingTeach && $trainingTasks === []) {
    return;
}
/** @var list<Participation> $trainingRows */
$trainingRows = $trainingTeach ? Participation::query()->with('course.type')->where('personnel_id', (int) $mitarbeiter->id)->orderByDesc('id')->get()->all() : [];
$trainingBase = defined('BASE_PATH') ? (string) BASE_PATH : '/';
?>
<div class="p-3 shadow-sm border ignis-card">
    <h4>Ausbildung</h4>
    <?php if ($trainingTasks !== []): ?>
        <div class="grid gap-2 mb-3">
            <?php foreach ($trainingTasks as $trainingTask): ?>
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <div><i class="fa-solid fa-list-check" aria-hidden="true"></i> <?= htmlspecialchars($trainingTask->title) ?>
                        <?php if ($trainingTask->due_on !== null): ?><span class="ignis-detail__muted">bis <?= htmlspecialchars(date('d.m.Y', (int) strtotime($trainingTask->due_on))) ?></span><?php endif; ?>
                        <?php if (!FollowUps::owns($trainingTask, $trainingUser) && $trainingTask->assignee !== null): ?><span class="ignis-detail__muted">· <?= htmlspecialchars($trainingTask->assignee) ?></span><?php endif; ?></div>
                    <form method="post" action="<?= $trainingBase ?>training/follow-ups/<?= (int) $trainingTask->id ?>/done">
                        <?= csrf_field() ?>
                        <input type="hidden" name="back" value="personnel/profile?id=<?= (int) $mitarbeiter->id ?>">
                        <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--secondary"><i class="fa-solid fa-check" aria-hidden="true"></i> Erledigt</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($trainingTeach && $trainingRows === []): ?>
        <p class="ignis-detail__muted m-0">Noch in keinem Lehrgang.</p>
    <?php elseif ($trainingRows !== []): ?>
        <table class="ignis-table">
            <tbody>
                <?php foreach ($trainingRows as $trainingRow): $trainingCourse = $trainingRow->course; ?>
                    <tr>
                        <td><?php if (TrainingPolicy::instruct($trainingCourse)): ?><a href="<?= $trainingBase ?>training/manage/<?= (int) $trainingCourse->id ?>"><?= htmlspecialchars($trainingCourse->title) ?></a><?php else: ?><?= htmlspecialchars($trainingCourse->title) ?><?php endif; ?>
                            <div class="ignis-detail__muted"><?= htmlspecialchars($trainingCourse->type->name) ?> · <?= htmlspecialchars($trainingCourse->period()) ?></div></td>
                        <td class="text-right"><span class="ignis-chip <?= View::participationChip($trainingRow->state()) ?>"><?= htmlspecialchars($trainingRow->state()->label()) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
