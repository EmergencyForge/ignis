<?php
/**
 * Dashboard: Einsätze als Schale mit zwei Sparklines, je Stunde seit 0 Uhr
 * und je Tag über sieben Tage. spark.js zeichnet die Linien aus
 * data-values, der Kopfwert ist der letzte Wert. Die Datenreihe ist Cyan,
 * Orange steht in keinem Diagramm. Eingebunden aus index.php.
 *
 * @var array{today:int, rd:?int, fire:?int, yesterday:int, hours:list<int>, days:list<int>}|null $dashboardIncidents
 * @var string $dashboardDelta
 */

if ($dashboardIncidents === null) {
    return;
}
$incidentHours = $dashboardIncidents['hours'];
$incidentDays  = $dashboardIncidents['days'];
?>
<section class="ignis-bezel" aria-labelledby="dashboard-incidents-title" data-ignis-reveal>
    <div class="ignis-bezel__head">
        <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
        <h2 class="ignis-bezel__title" id="dashboard-incidents-title">Einsätze</h2>
    </div>
    <div class="ignis-bezel__well">
        <div class="ignis-sparks">
            <div class="ignis-spark" data-ignis-spark data-values="<?= implode(',', $incidentHours) ?>" data-label="Einsätze je Stunde seit 0 Uhr">
                <div class="ignis-spark__head"><span class="ignis-spark__label">Einsätze/Std</span><b class="ignis-spark__value"><?= (int) end($incidentHours) ?></b></div>
            </div>
            <div class="ignis-spark" data-ignis-spark data-values="<?= implode(',', $incidentDays) ?>" data-label="Einsätze je Tag in den letzten sieben Tagen">
                <div class="ignis-spark__head"><span class="ignis-spark__label">Einsätze/Tag</span><b class="ignis-spark__value"><?= (int) end($incidentDays) ?></b></div>
            </div>
        </div>
    </div>
    <div class="ignis-bezel__foot"><?= $dashboardDelta ?>ggü. gestern um diese Zeit</div>
</section>
