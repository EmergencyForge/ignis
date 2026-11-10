<?php
/**
 * Spalte „Richtig“ in der Fragenliste: Balken, Anteil und Markierung.
 *
 * @var \EmergencyForge\Training\Exam\QuestionStats          $stat
 * @var \EmergencyForge\Training\Exam\QuestionVerdict|null   $verdict
 */

use EmergencyForge\Training\Exam\QuestionStats;

$share = $stat->correctShare();
?>
<?php if ($share === null): ?>
    <span class="ignis-detail__muted">noch nicht gestellt</span>
<?php else: ?>
    <?php $percent = (int) round($share * 100); ?>
    <div class="tr-share">
        <?php if ($verdict !== null): ?>
            <span class="ignis-chip ignis-chip--sm ignis-chip--warn"><?= htmlspecialchars($verdict->label()) ?></span>
        <?php elseif ($stat->asked() < QuestionStats::MIN_ASKED): ?>
            <span class="ignis-detail__muted" data-ignis-tooltip="Erst ab <?= QuestionStats::MIN_ASKED ?> Versuchen werden zu schwere oder zu leichte Fragen markiert.">wenig Daten</span>
        <?php endif; ?>
        <span class="tr-bar" aria-hidden="true"><span class="<?= $verdict !== null ? 'is-warn' : '' ?>" style="width: <?= $percent ?>%"></span></span>
        <span><?= $percent ?> %</span>
    </div>
<?php endif; ?>
