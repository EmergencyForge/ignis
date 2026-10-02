<?php
/**
 * Dashboard: Fahrzeuge als Schale. Oben die Strichskala „einsatzbereit“
 * (Status 1 und 2 unter allen mit gemeldetem Status, ein Strich je
 * Fahrzeug, ab 40 Fahrzeugen 40 Striche), darunter die fünf zuletzt
 * geänderten Fahrzeuge mit ihrem FMS-Status als Chip. Eingebunden aus
 * index.php.
 *
 * @var array{total:int, ready:int, unknown:int, rows:list<array{name:string, rd_type:int, status:string, since:?DateTimeImmutable, station:string}>, down:list<array<string,mixed>>}|null $dashboardVehicles
 * @var DateTimeImmutable $dashboardNow
 */

use App\Support\Overview;

if ($dashboardVehicles === null) {
    return;
}
$vehicleTicks = min($dashboardVehicles['total'], 40);
$vehicleOn    = (int) round($dashboardVehicles['ready'] / $dashboardVehicles['total'] * $vehicleTicks);
$vehicleIcons = [1 => 'fa-truck-medical', 2 => 'fa-truck-medical', 3 => 'fa-truck'];
?>
<section class="ignis-bezel" aria-labelledby="dashboard-vehicles-title" data-ignis-reveal>
    <div class="ignis-bezel__head">
        <i class="fa-solid fa-truck-medical" aria-hidden="true"></i>
        <h2 class="ignis-bezel__title" id="dashboard-vehicles-title">Fahrzeuge <span class="ignis-count"><?= $dashboardVehicles['total'] ?></span></h2>
        <div class="ignis-bezel__actions">
            <a class="ignis-btn ignis-btn--secondary ignis-btn--sm" href="<?= BASE_PATH ?>settings/vehicles/vehicles/index">Alle</a>
        </div>
    </div>
    <div class="ignis-bezel__well ignis-bezel__well--list">
        <div class="ignis-meter" role="meter" aria-valuenow="<?= $dashboardVehicles['ready'] ?>" aria-valuemin="0" aria-valuemax="<?= $dashboardVehicles['total'] ?>" aria-label="Fahrzeuge einsatzbereit, <?= $dashboardVehicles['ready'] ?> von <?= $dashboardVehicles['total'] ?>">
            <div class="ignis-meter__head"><span>Fahrzeuge einsatzbereit</span><b class="ignis-meter__value"><?= $dashboardVehicles['ready'] ?> von <?= $dashboardVehicles['total'] ?></b></div>
            <div class="ignis-meter__scale" aria-hidden="true"><?= str_repeat('<span class="ignis-meter__tick is-on"></span>', $vehicleOn) . str_repeat('<span class="ignis-meter__tick"></span>', $vehicleTicks - $vehicleOn) ?></div>
        </div>
        <?php foreach ($dashboardVehicles['rows'] as $vehicleRow):
            $vehicleStatus = Overview::status($vehicleRow['status']);
            $vehicleMeta   = array_filter([
                $vehicleRow['station'],
                $vehicleRow['since'] !== null ? 'seit ' . Overview::since($vehicleRow['since'], $dashboardNow) : '',
            ]);
        ?>
            <div class="ignis-bezel__item ignis-entry">
                <span class="ignis-glyph ignis-glyph--sm" aria-hidden="true"><i class="fa-solid <?= $vehicleIcons[$vehicleRow['rd_type']] ?? 'fa-car' ?>"></i></span>
                <span class="ignis-entry__title ignis-mono"><?= htmlspecialchars($vehicleRow['name']) ?></span>
                <span class="ignis-chip ignis-chip--sm"<?= $vehicleStatus['tone'] !== null ? ' data-tone="' . $vehicleStatus['tone'] . '"' : '' ?>><?= $vehicleRow['status'] === '6' ? '<i class="fa-solid fa-ban" aria-hidden="true"></i>' : '' ?><b><?= htmlspecialchars($vehicleRow['status']) ?></b> <?= htmlspecialchars($vehicleStatus['label']) ?></span>
                <?php if ($vehicleMeta !== []): ?>
                    <span class="ignis-entry__meta"><?= htmlspecialchars(implode(' · ', $vehicleMeta)) ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
