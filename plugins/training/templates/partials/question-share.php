<?php
/**
 * Anteil richtig mit Markierung, in der Fragenliste und auf der Seite der Frage.
 *
 * @var \EmergencyForge\Training\Exam\QuestionStats          $stat
 * @var \EmergencyForge\Training\Exam\QuestionVerdict|null   $verdict
 */

use EmergencyForge\Training\Exam\QuestionVerdict;

$share = $stat->correctShare();
?>
<?php if ($share === null): ?><span class="ignis-sr-only">noch keine bewerteten Antworten</span><?php else: ?><?= (int) round($share * 100) ?> %<?php endif; ?>
<?php if ($verdict !== null): ?>
    <span class="ignis-chip ignis-chip--sm <?= $verdict === QuestionVerdict::TooHard ? 'ignis-chip--danger' : 'ignis-chip--info' ?>"><?= htmlspecialchars($verdict->label()) ?></span>
<?php endif; ?>
