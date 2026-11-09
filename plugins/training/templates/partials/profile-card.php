<?php
/**
 * Profilkarte „Ausbildung“: die Lehrgänge des Mitarbeiters mit Status.
 * Sichtbar für alle, die das Profil sehen und Lehrgänge leiten oder
 * verwalten; das Profil selbst prüft die Rechte für den Rest.
 *
 * @var \App\Models\Personnel $mitarbeiter
 */

use Plugin\Training\Models\Participation;
use Plugin\Training\Policies\TrainingPolicy;
use Plugin\Training\View;

if (!TrainingPolicy::teach()) {
    return;
}
/** @var list<Participation> $trainingRows */
$trainingRows = Participation::query()->with('course.type')->where('personnel_id', (int) $mitarbeiter->id)->orderByDesc('id')->get()->all();
$trainingBase = defined('BASE_PATH') ? (string) BASE_PATH : '/';
?>
<div class="p-3 shadow-sm border ignis-card">
    <h4>Ausbildung</h4>
    <?php if ($trainingRows === []): ?>
        <p class="ignis-detail__muted m-0">Noch in keinem Lehrgang.</p>
    <?php else: ?>
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
