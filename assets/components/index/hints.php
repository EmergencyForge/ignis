<?php
/**
 * Dashboard: Hinweise als Schale in der rechten Spalte. Jeder Hinweis ist
 * eine Regel aus den Daten: offene eNOTF-Protokolle, Fahrzeuge in Status
 * 6 und die offenen Aufgaben des Betrachters (App\Support\OpenTasks). Ist
 * nichts davon offen, steht dort ein ruhiger Hinweis. Ohne Recht auf eine
 * der Quellen entfällt die Schale. Eingebunden aus index.php.
 *
 * @var \App\Plugins\PluginLoader $dashboardPlugins
 * @var array{total:int, ready:int, unknown:int, rows:list<array<string,mixed>>, down:list<array{name:string, since:?DateTimeImmutable}>}|null $dashboardVehicles
 * @var array{open:int, oldest:?DateTimeImmutable, week:int, weekReleased:int}|null $dashboardProtocols
 * @var DateTimeImmutable $dashboardNow
 */

use App\Support\Overview;

$hintTasksFailed = false;
try {
    $hintTasks = \App\Support\OpenTasks::forCurrentUser($dashboardPlugins);
} catch (\Throwable $error) {
    \App\Logging\Logger::error('Dashboard-Aufgaben konnten nicht geladen werden', ['error' => $error->getMessage()]);
    $hintTasks = [];
    $hintTasksFailed = true;
}
if ($hintTasks === null && $dashboardProtocols === null && $dashboardVehicles === null) {
    return;
}

$hintDown = $dashboardVehicles['down'] ?? [];
$hintOpen = $dashboardProtocols['open'] ?? 0;
$hintAny  = $hintOpen > 0 || $hintDown !== [] || ($hintTasks ?? []) !== [] || $hintTasksFailed;
?>
<section class="ignis-bezel ignis-hints" aria-labelledby="dashboard-hints-title" data-ignis-reveal>
    <div class="ignis-bezel__head">
        <i class="fa-solid fa-lightbulb" aria-hidden="true"></i>
        <h2 class="ignis-bezel__title" id="dashboard-hints-title">Hinweise</h2>
    </div>
    <div class="ignis-bezel__well">
        <?php if ($hintOpen > 0 && $dashboardProtocols !== null): ?>
            <div class="ignis-hint">
                <div class="ignis-hint__head">
                    <span class="ignis-glyph ignis-glyph--sm" data-tone="danger" aria-hidden="true"><i class="fa-solid fa-circle-exclamation"></i></span>
                    <h3 class="ignis-hint__title"><?= $hintOpen === 1 ? 'Ein eNOTF-Protokoll' : $hintOpen . ' eNOTF-Protokolle' ?> nicht freigegeben</h3>
                </div>
                <?php if ($dashboardProtocols['oldest'] !== null): ?>
                    <p class="ignis-hint__text"><?= $hintOpen === 1 ? 'Es ist' : 'Das älteste ist' ?> seit <?= Overview::since($dashboardProtocols['oldest'], $dashboardNow) ?> offen.</p>
                <?php endif; ?>
                <?php if ($dashboardProtocols['week'] > 0):
                    $hintShare = (int) round($dashboardProtocols['weekReleased'] / $dashboardProtocols['week'] * 100);
                ?>
                    <div class="ignis-progress ignis-progress--sm ignis-progress--labeled-head" role="progressbar" aria-valuenow="<?= $hintShare ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= $hintShare ?> % der Protokolle dieser Woche freigegeben" style="--value:<?= $hintShare / 100 ?>">
                        <div class="ignis-progress__bar"></div>
                        <span class="ignis-progress__value" aria-hidden="true"><?= $hintShare ?> %</span>
                    </div>
                <?php endif; ?>
                <a class="ignis-btn ignis-btn--accent-soft ignis-btn--sm ignis-hint__action" href="<?= BASE_PATH ?>enotf/admin/list">Protokolle ansehen</a>
            </div>
        <?php endif; ?>
        <?php if ($hintDown !== []): ?>
            <div class="ignis-hint">
                <div class="ignis-hint__head">
                    <span class="ignis-glyph ignis-glyph--sm" data-tone="warn" aria-hidden="true"><i class="fa-solid fa-clock"></i></span>
                    <h3 class="ignis-hint__title">
                        <?php if (count($hintDown) === 1): ?>
                            <span class="ignis-mono"><?= htmlspecialchars($hintDown[0]['name']) ?></span><?= $hintDown[0]['since'] !== null ? ' seit ' . Overview::since($hintDown[0]['since'], $dashboardNow) : '' ?> Status 6
                        <?php else: ?>
                            <?= count($hintDown) ?> Fahrzeuge in Status 6
                        <?php endif; ?>
                    </h3>
                </div>
                <p class="ignis-hint__text"><?= count($hintDown) === 1 ? 'Nicht einsatzbereit.' : 'Nicht einsatzbereit: ' . htmlspecialchars(implode(', ', array_column($hintDown, 'name'))) . '.' ?></p>
            </div>
        <?php endif; ?>
        <?php if ($hintTasksFailed): ?>
            <div class="ignis-hint" role="alert">
                <div class="ignis-hint__head">
                    <span class="ignis-glyph ignis-glyph--sm" data-tone="danger" aria-hidden="true"><i class="fa-solid fa-circle-xmark"></i></span>
                    <h3 class="ignis-hint__title">Aufgaben nicht geladen</h3>
                </div>
                <p class="ignis-hint__text">Deine offenen Aufgaben konnten nicht geladen werden. Lade die Seite neu.</p>
            </div>
        <?php endif; ?>
        <?php foreach ($hintTasks ?? [] as $hintTask): ?>
            <div class="ignis-hint">
                <div class="ignis-hint__head">
                    <span class="ignis-glyph ignis-glyph--sm" data-tone="info" aria-hidden="true"><i class="fa-solid fa-list-check"></i></span>
                    <h3 class="ignis-hint__title"><a href="<?= htmlspecialchars($hintTask['href'], ENT_QUOTES) ?>"><?= htmlspecialchars($hintTask['label']) ?></a></h3>
                </div>
                <p class="ignis-hint__text"><?= htmlspecialchars($hintTask['reason']) ?></p>
            </div>
        <?php endforeach; ?>
        <?php if (!$hintAny): ?>
            <div class="ignis-hint">
                <div class="ignis-hint__head">
                    <span class="ignis-glyph ignis-glyph--sm" data-tone="ok" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
                    <h3 class="ignis-hint__title">Nichts wartet auf dich</h3>
                </div>
                <p class="ignis-hint__text">Offene Protokolle, Fahrzeuge außer Dienst und deine Aufgaben erscheinen hier.</p>
            </div>
        <?php endif; ?>
    </div>
</section>
