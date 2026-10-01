<?php
/**
 * Dashboard: Kennzahl-Kacheln. Einsätze heute, Fahrzeuge einsatzbereit und
 * die Alarmkachel „eNOTF-Protokolle offen“, jede nur mit Recht und Daten
 * (App\Support\Overview). Die Alarmkachel ist rot, solange etwas offen ist,
 * bei 0 eine ruhige Kachel mit Haken. Eingebunden aus index.php.
 *
 * @var array{today:int, rd:?int, fire:?int, yesterday:int, hours:list<int>, days:list<int>}|null $dashboardIncidents
 * @var array{total:int, ready:int, unknown:int, rows:list<array<string,mixed>>, down:list<array<string,mixed>>}|null $dashboardVehicles
 * @var array{open:int, oldest:?DateTimeImmutable, week:int, weekReleased:int}|null $dashboardProtocols
 * @var string $dashboardDelta
 * @var DateTimeImmutable $dashboardNow
 */

use App\Support\Overview;

if ($dashboardIncidents === null && $dashboardVehicles === null && $dashboardProtocols === null) {
    return;
}
?>
<div class="ignis-kpis">
    <?php if ($dashboardIncidents !== null): ?>
        <div class="ignis-kpi" data-ignis-reveal>
            <div class="ignis-kpi__top">
                <span class="ignis-kpi__label"><i class="fa-solid fa-truck-fast" aria-hidden="true"></i>Einsätze heute</span>
                <?= $dashboardDelta ?>
            </div>
            <div class="ignis-kpi__value"><span data-ignis-count><?= $dashboardIncidents['today'] ?></span></div>
            <div class="ignis-kpi__sub"><?= match (true) {
                $dashboardIncidents['rd'] === null   => 'Feuerwehr',
                $dashboardIncidents['fire'] === null => 'Rettungsdienst',
                default                              => 'davon ' . $dashboardIncidents['rd'] . ' Rettungsdienst',
            } ?></div>
        </div>
    <?php endif; ?>
    <?php if ($dashboardVehicles !== null): ?>
        <a class="ignis-kpi" href="<?= BASE_PATH ?>settings/vehicles/vehicles/index" data-ignis-reveal>
            <div class="ignis-kpi__top">
                <span class="ignis-kpi__label">Fahrzeuge einsatzbereit</span>
                <span class="ignis-glyph ignis-glyph--sm" aria-hidden="true"><i class="fa-solid fa-truck-medical"></i></span>
            </div>
            <div class="ignis-kpi__value"><span data-ignis-count><?= $dashboardVehicles['ready'] ?></span> <span class="ignis-unit">von <?= $dashboardVehicles['total'] ?></span></div>
            <div class="ignis-kpi__sub"><?= $dashboardVehicles['unknown'] > 0
                ? $dashboardVehicles['unknown'] . ' ohne Statusmeldung'
                : count($dashboardVehicles['down']) . ' nicht einsatzbereit' ?></div>
            <i class="fa-solid fa-arrow-right ignis-kpi__arrow" aria-hidden="true"></i>
        </a>
    <?php endif; ?>
    <?php if ($dashboardProtocols !== null): ?>
        <?php $dashboardOpen = $dashboardProtocols['open']; ?>
        <a class="ignis-kpi" href="<?= BASE_PATH ?>enotf/admin/list?view=2" data-tone="<?= $dashboardOpen > 0 ? 'danger' : 'ok' ?>" data-ignis-reveal>
            <div class="ignis-kpi__top">
                <span class="ignis-kpi__label"><i class="fa-solid <?= $dashboardOpen > 0 ? 'fa-triangle-exclamation' : 'fa-circle-check' ?>" aria-hidden="true"></i>eNOTF-Protokolle offen</span>
            </div>
            <div class="ignis-kpi__value"><span data-ignis-count><?= $dashboardOpen ?></span></div>
            <div class="ignis-kpi__sub"><?= $dashboardProtocols['oldest'] !== null
                ? 'ältestes seit ' . Overview::since($dashboardProtocols['oldest'], $dashboardNow)
                : 'alle freigegeben' ?></div>
            <i class="fa-solid fa-arrow-right ignis-kpi__arrow" aria-hidden="true"></i>
        </a>
    <?php endif; ?>
</div>
