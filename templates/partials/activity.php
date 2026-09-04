<?php

declare(strict_types=1);

/**
 * Aktivität in der Seitenspalte einer Detailseite (App\Support\Activity).
 *
 * Wird per require aus der Detailseite eingebunden und teilt deren Scope.
 * Erwartet:
 *
 *   @var list<array{label:string, actor:?string, at:string}> $activityEntries
 *
 * Ein Eintrag je Zeile: was, von wem, wann. Kein Link je Eintrag, die Zeile
 * ist eine Notiz; die vollständige Auswertung liegt im Audit-Log.
 */
?>
<?php if ($activityEntries === []): ?>
    <p class="ignis-detail__muted">Noch keine Aktivität.</p>
<?php else: ?>
    <ul class="ignis-timeline">
        <?php foreach ($activityEntries as $activityEntry): ?>
            <li class="ignis-timeline__item">
                <span class="ignis-timeline__text"><?= htmlspecialchars($activityEntry['label']) ?><?= $activityEntry['actor'] !== null ? ' <span class="ignis-detail__muted">von ' . htmlspecialchars($activityEntry['actor']) . '</span>' : '' ?></span>
                <time class="ignis-timeline__when" datetime="<?= htmlspecialchars(date('c', strtotime($activityEntry['at']) ?: 0)) ?>"><?= htmlspecialchars(\App\Helpers\DateTimeHelper::formatShortLocal($activityEntry['at'])) ?></time>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
