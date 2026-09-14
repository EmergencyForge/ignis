<?php
$taskLoadFailed = false;
try {
    $openTasks = \App\Support\OpenTasks::forCurrentUser($dashboardPlugins);
} catch (\Throwable $error) {
    \App\Logging\Logger::error('Dashboard-Aufgaben konnten nicht geladen werden', ['error' => $error->getMessage()]);
    $openTasks = [];
    $taskLoadFailed = true;
}
?>
<?php if ($openTasks !== null): ?>
<section class="ignis-card mb-4" aria-labelledby="open-tasks-title">
    <div class="ignis-card__header"><h2 class="ignis-card__title" id="open-tasks-title">Für dich offen</h2></div>
    <div class="ignis-card__body ignis-tasks">
        <?php if ($taskLoadFailed): ?>
            <p role="alert">Deine Aufgaben konnten nicht geladen werden. Bitte lade die Seite erneut.</p>
        <?php elseif ($openTasks === []): ?>
            <p class="m-0">Alles erledigt — aktuell gibt es hier nichts für dich zu bearbeiten.</p>
        <?php else: ?>
            <?php foreach ($openTasks as $task): ?>
                <a class="ignis-task" href="<?= htmlspecialchars($task['href'], ENT_QUOTES) ?>"><span><strong><?= htmlspecialchars($task['label']) ?></strong><small><?= htmlspecialchars($task['reason']) ?></small></span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>
