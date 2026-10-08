<?php

/**
 * Section: Verlauf (Vitalwerte)
 *
 * Nachbau der v1-Seiten plugins/enotf/templates/enotf/protokoll/verlauf/:
 *
 *   ÜBERSICHT (ohne ?t): index.php mit Kopfzeile („Verlauf bearbeiten"),
 *     Statistik-Box, Chart.js-Vitalwerte-Chart (Linien je Parameter,
 *     gruppiert nach Zeitpunkt, zwei Y-Achsen wie v1) aus dem lokalen
 *     vendor-chart-Bundle (kein CDN, rendert auch ohne Außenanbindung).
 *     Darunter zusätzlich die chronologische Werte-Tabelle.
 *
 *   ?t=add  (v1 add.php): Keypad-Maske mit Vitalparameter-Boxen, Zahlenfeld,
 *     Range-Strip mit Normbereichs-Färbung. Speichern schickt EINEN
 *     Request an die v2-Vitals-API (POST /api/enotf/vitals, BZ in der
 *     Anzeige-Einheit, die API konvertiert nach mg/dl).
 *
 *   ?t=list (v1 list.php): gruppierte Tabelle mit Einzelwert-Löschung,
 *     Soft-Delete über POST /api/enotf/vitals/delete (statt v1s
 *     GET ?action=delete).
 *
 * Die Keypad-/Vitalparameter-Styles kommen aus divi.css und sind auf
 * body[data-page="verlauf"] gescoped, das Layout setzt data-page auf
 * den Section-Key, hier also automatisch "verlauf".
 *
 * @var array<string,mixed> $protokoll
 * @var string $enr
 * @var bool $istGesperrt
 * @var string|null $fokusThema
 */

use Plugin\Enotf\Helpers\BloodSugarHelper;
use Plugin\Enotf\Crew\Catalogs\VitalparameterCatalog;
use Plugin\Enotf\Helpers\EnotfUrl;
use Plugin\Enotf\Crew\Models\EdiviVitalwert;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);

$sectionUrl = EnotfUrl::protokoll($enr, 'verlauf');
$fokusUrl   = static fn (string $t): string => $sectionUrl . '?t=' . rawurlencode($t);

$fokus = $fokusThema ?? null;
if (!in_array($fokus, ['add', 'list'], true)) {
    $fokus = null;
}
if ($fokus === 'add' && $istGesperrt) {
    // v1 add.php leitet bei freigegebenem Protokoll auf die Übersicht um
    $fokus = null;
}

// Keypad-Maske ist wie v1 (verlauf/add.php) chromelos: keine Topbar/
// Section-Nav, Rückweg über Abbrechen/Speichern (?t=list behält wie
// v1 list.php das volle Chrome)
if ($fokus === 'add') {
    $sectionChromeless = true;
}

$bzHelper = new BloodSugarHelper();
$bzUnit   = $bzHelper->getCurrentUnit();

// ── Werte laden (nur aktive), nach Zeitpunkt gruppieren ──────────────
$werteRaw = EdiviVitalwert::aktiv()
    ->where('enr', $enr)
    ->orderBy('zeitpunkt')
    ->orderBy('id')
    ->get();

$gruppen = []; // zeitpunkt => [ { id, name, wert_anzeige, einheit_anzeige } ]
foreach ($werteRaw as $wert) {
    $zeitpunkt = (string) $wert->zeitpunkt;
    $code      = VitalparameterCatalog::fromLegacyName((string) $wert->parameter_name);

    $anzeige        = (string) $wert->parameter_wert;
    $anzeigeEinheit = (string) $wert->parameter_einheit;
    if ($code === 'bz') {
        // Speicherung immer mg/dl, Anzeige in der konfigurierten Einheit
        $anzeige        = $bzHelper->formatValue($wert->parameter_wert, false);
        $anzeigeEinheit = $bzUnit;
    }

    $gruppen[$zeitpunkt][] = [
        'id'      => (int) $wert->id,
        'name'    => (string) $wert->parameter_name,
        'wert'    => $anzeige,
        'einheit' => $anzeigeEinheit,
    ];
}
ksort($gruppen);

// ── Chart-Daten (v1 verlauf/index.php): je Parameter eine Serie,
//    gruppiert nach Zeitpunkt, BZ in der Anzeige-Einheit ────────────
$chartParams = ['spo2', 'atemfreq', 'etco2', 'herzfreq', 'rrsys', 'rrdias', 'bz', 'temp'];
$chartJeZeit = []; // zeitpunkt => code => float
foreach ($werteRaw as $wert) {
    $code = VitalparameterCatalog::fromLegacyName((string) $wert->parameter_name);
    if ($code === null || !in_array($code, $chartParams, true)) {
        continue; // Bemerkungen u.ä. gehören nur in die Tabelle
    }
    $roh = str_replace(',', '.', trim((string) $wert->parameter_wert));
    if ($roh === '' || !is_numeric($roh)) {
        continue; // nicht-numerische Einträge ("ng"/"nm") nicht plotten
    }
    $chartJeZeit[(string) $wert->zeitpunkt][$code] = $code === 'bz'
        ? $bzHelper->toDisplayUnit($wert->parameter_wert)
        : (float) $roh;
}
ksort($chartJeZeit);

$chartLabels = [];
$chartSeries = array_fill_keys($chartParams, []);
foreach ($chartJeZeit as $chartZeit => $chartWerte) {
    $dt = date_create((string) $chartZeit);
    $chartLabels[] = $dt ? $dt->format('H:i') : (string) $chartZeit;
    foreach ($chartParams as $code) {
        $chartSeries[$code][] = $chartWerte[$code] ?? null;
    }
}

$totalWerte    = count($werteRaw);
$anzahlZeiten  = count($gruppen);
$zeitFormat    = static function (string $zeitpunkt): string {
    $dt = date_create($zeitpunkt);
    return $dt ? $dt->format('d.m.Y H:i') : $zeitpunkt;
};

// v1-Tabellen-Styles (list.php), von Übersicht und Bearbeiten-Ansicht geteilt
$tabelleStyles = <<<'CSS'
<style>
    .vitals-container {
        background: rgba(0, 0, 0, 0.2);
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .vitals-table { width: 100%; margin: 0; background: transparent; }
    .vitals-table thead th {
        background: rgba(255, 255, 255, 0.08);
        border-bottom: 2px solid rgba(255, 255, 255, 0.15);
        color: white; font-weight: 600; padding: 12px 8px; font-size: 13px;
        border: none; position: sticky; top: 0; z-index: 10;
    }
    .vitals-table tbody tr {
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        transition: background-color 0.2s ease;
    }
    .vitals-table tbody tr:hover { background: rgba(255, 255, 255, 0.05); }
    .vitals-table tbody td {
        padding: 8px; color: white; font-size: 13px; border: none; vertical-align: middle;
    }
    .time-group { background: rgba(255, 255, 255, 0.08); border-top: 2px solid rgba(255, 255, 255, 0.2); }
    .time-group td { font-weight: 600; color: rgba(255, 255, 255, 0.9); padding: 10px 8px !important; font-size: 14px; }
    .parameter-name { font-weight: 500; color: white; }
    .parameter-value { font-weight: 600; color: #4CAF50; font-size: 14px; }
    .parameter-unit { color: rgba(255, 255, 255, 0.7); font-size: 12px; margin-left: 2px; }
    .btn-delete-compact {
        background: rgba(220, 53, 69, 0.8); border: none; color: white;
        padding: 4px 8px; border-radius: 4px; font-size: 11px; text-decoration: none;
        transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 3px;
        cursor: pointer;
    }
    .btn-delete-compact:hover { background: rgba(220, 53, 69, 1); color: white; transform: scale(1.05); }
    .no-data-compact {
        background: rgba(108, 117, 125, 0.1); border: 1px solid rgba(108, 117, 125, 0.3);
        color: white; padding: 40px 20px; border-radius: 8px; text-align: center;
    }
    .vitals-info {
        background: rgba(255, 255, 255, 0.1); border-radius: 8px;
        padding: 15px; margin-bottom: 20px;
    }
    .vitals-stat {
        display: inline-block; background: rgba(0, 123, 255, 0.2); color: white;
        padding: 5px 15px; border-radius: 20px; margin-right: 10px; font-size: 14px;
    }
</style>
CSS;

/**
 * Gruppierte v1-Tabelle (list.php-Markup). $mitLoeschen rendert die
 * Aktionsspalte mit Soft-Delete-Buttons (nur Bearbeiten-Ansicht).
 */
$renderTabelle = static function (array $gruppen, bool $mitLoeschen) use ($e, $zeitFormat): void {
    ?>
    <div class="vitals-container">
        <table class="vitals-table">
            <thead>
                <tr>
                    <th style="width: 30%;">Parameter</th>
                    <th style="width: 15%;">Wert</th>
                    <th style="width: 10%;">Einheit</th>
                    <th style="width: <?= $mitLoeschen ? '25%' : '45%' ?>;"></th>
                    <?php if ($mitLoeschen): ?>
                        <th style="width: 20%;">Aktion</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($gruppen as $zeitpunkt => $eintraege): ?>
                    <tr class="time-group">
                        <td colspan="<?= $mitLoeschen ? 5 : 4 ?>">
                            <i class="fa-regular fa-clock"></i> <?= $e($zeitFormat((string) $zeitpunkt)) ?> Uhr
                            <small style="margin-left: 15px; color: rgba(255,255,255,0.6);">
                                (<?= count($eintraege) ?> Parameter)
                            </small>
                        </td>
                    </tr>
                    <?php foreach ($eintraege as $eintrag): ?>
                        <tr>
                            <td class="parameter-name"><?= $e($eintrag['name']) ?></td>
                            <td class="parameter-value"><?= $e($eintrag['wert']) ?></td>
                            <td class="parameter-unit"><?= $e($eintrag['einheit']) ?></td>
                            <td></td>
                            <?php if ($mitLoeschen): ?>
                                <td>
                                    <button type="button" class="btn-delete-compact"
                                            data-vital-delete="<?= $eintrag['id'] ?>"
                                            data-vital-label="<?= $e($eintrag['name']) ?> (<?= $e($eintrag['wert']) ?> <?= $e($eintrag['einheit']) ?>)">
                                        <i class="fa-solid fa-trash"></i>
                                        Löschen
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
};
?>

<?php if ($fokus === null): ?>

    <!-- ── ÜBERSICHT (v1 verlauf/index.php) ── -->
    <?= $tabelleStyles ?>
    <div class="row my-3" style="margin-left: 0">
        <div class="col">
            <div class="flex justify-content-between align-items-center">
                <div class="flex gap-2">
                    <a href="<?= $e($fokusUrl('list')) ?>" class="ignis-btn ignis-btn--ghost">
                        <i class="fa-solid fa-list"></i> Verlauf bearbeiten
                    </a>
                    <?php if (!$istGesperrt): ?>
                        <a href="<?= $e($fokusUrl('add')) ?>" class="ignis-btn ignis-btn--ghost">
                            <i class="fa-solid fa-plus"></i> Werte hinzufügen
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row" style="margin-left: 0">
        <div class="col">
            <div class="vitals-info">
                <h6 class="text-light mb-2">
                    <i class="fa-solid fa-chart-line"></i> Vitalparameter-Übersicht
                </h6>
                <div>
                    <span class="vitals-stat">
                        <i class="fa-solid fa-database"></i> <?= $totalWerte ?> Einzelwerte erfasst
                    </span>
                    <span class="vitals-stat">
                        <i class="fa-regular fa-clock"></i> <?= $anzahlZeiten ?> Zeitpunkte dokumentiert
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart-Styles (v1 verlauf/index.php) -->
    <style>
        .chart-container {
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .chart-container:hover {
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
        }

        .chart-click-hint {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .chart-container:hover .chart-click-hint {
            opacity: 1;
        }

        .legend-toggle {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 5px 10px;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 12px;
        }

        .legend-item:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .legend-item.hidden {
            opacity: 0.5;
            text-decoration: line-through;
        }
    </style>

    <!-- Kombinierter Chart (v1 verlauf/index.php) -->
    <div class="row" style="margin-left: 0">
        <div class="col">
            <div class="row edivi__box">
                <h5 class="text-light px-2 py-1">Alle Vitalparameter</h5>
                <div class="col p-3">
                    <div class="legend-toggle" id="legendToggle">
                        <!-- Wird durch JavaScript gefüllt -->
                    </div>
                    <div class="chart-container position-relative" id="chartContainer">
                        <?php if (!$istGesperrt): ?>
                            <div class="chart-click-hint">
                                <i class="fa-solid fa-plus"></i> Klicken zum Hinzufügen
                            </div>
                        <?php endif; ?>
                        <canvas id="chartCombined" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row" style="margin-left: 0">
        <div class="col">
            <?php if ($gruppen === []): ?>
                <div class="no-data-compact">
                    <i class="fa-solid fa-circle-info" style="font-size: 32px; margin-bottom: 10px;"></i>
                    <h6>Noch keine Vitalparameter dokumentiert</h6>
                    <p class="mb-0" style="font-size: 13px;">
                        <?php if (!$istGesperrt): ?>
                            Klicken Sie auf "Werte hinzufügen", um die ersten Vitalparameter zu erfassen.
                        <?php else: ?>
                            Für diese Dokumentation wurden keine Vitalparameter erfasst.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="row edivi__box">
                    <h5 class="text-light px-2 py-1">Werte im Detail</h5>
                    <div class="col p-3">
                        <?php $renderTabelle($gruppen, false); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Chart.js aus dem lokalen Bundle (kein CDN) -->
    <script src="<?= $e(asset('public/assets/dist/vendor-chart.js')) ?>"></script>
    <script>
        // Vitalwerte-Chart (v1 verlauf/index.php): Linien je Parameter,
        // gruppiert nach Zeitpunkt, zwei Y-Achsen, Legende mit Toggle.
        (function () {
            'use strict';
            var chartLabels = <?= json_encode($chartLabels) ?>;
            var chartData = <?= json_encode($chartSeries) ?>;
            var bzUnit = <?= json_encode($bzUnit) ?>;
            var IST_GESPERRT = <?= $istGesperrt ? 'true' : 'false' ?>;
            var ADD_URL = <?= json_encode($fokusUrl('add')) ?>;

            var container = document.getElementById('chartContainer');
            var canvas = document.getElementById('chartCombined');
            if (!canvas) return;

            // Chart-Klick: Werte hinzufügen (v1 addValues())
            if (container) {
                container.addEventListener('click', function () {
                    if (!IST_GESPERRT) {
                        window.location.href = ADD_URL;
                    } else if (window.Dialog) {
                        window.Dialog.alert('Diese Dokumentation ist bereits freigegeben und kann nicht mehr bearbeitet werden.', {
                            type: 'warning',
                            title: 'Nicht bearbeitbar'
                        });
                    }
                });
            }

            // Bundle nicht gebaut/eingebunden? Dann bleibt der Canvas
            // leer, die Tabelle darunter zeigt die Werte weiterhin.
            if (typeof window.Chart === 'undefined') {
                console.warn('Chart.js nicht geladen, Vitalwerte-Diagramm wird übersprungen.');
                return;
            }

            var parameterConfig = {
                rrsys: { axis: 'y1', color: 'rgb(255, 99, 132)', label: 'RR systolisch (mmHg)' },
                rrdias: { axis: 'y1', color: 'rgb(54, 162, 235)', label: 'RR diastolisch (mmHg)' },
                herzfreq: { axis: 'y1', color: 'rgb(255, 205, 86)', label: 'Herzfrequenz (/min)' },
                bz: { axis: 'y1', color: 'rgb(83, 102, 255)', label: 'Blutzucker (' + bzUnit + ')' },
                etco2: { axis: 'y', color: 'rgb(199, 199, 199)', label: 'etCO₂ (mmHg)' },
                spo2: { axis: 'y', color: 'rgb(75, 192, 192)', label: 'SpO₂ (%)' },
                atemfreq: { axis: 'y', color: 'rgb(153, 102, 255)', label: 'Atemfrequenz (/min)' },
                temp: { axis: 'y', color: 'rgb(255, 159, 64)', label: 'Temperatur (°C)' }
            };

            var datasets = [];
            Object.keys(parameterConfig).forEach(function (paramKey) {
                var config = parameterConfig[paramKey];
                var data = chartData[paramKey] || [];
                var hasData = data.some(function (v) { return v !== null && v !== undefined; });
                if (!hasData) return;

                datasets.push({
                    label: config.label,
                    data: data,
                    borderColor: config.color,
                    backgroundColor: config.color.replace('rgb', 'rgba').replace(')', ', 0.1)'),
                    tension: 0.4,
                    yAxisID: config.axis,
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    borderWidth: 3,
                    hidden: false,
                    spanGaps: true,
                    parameterKey: paramKey
                });
            });

            // BZ-Achse: Standardbereich, bei Ausreißern erweitert (v1)
            var bzValues = (chartData.bz || []).filter(function (v) { return v !== null && v !== undefined; });
            var rightAxisMax = bzUnit === 'mmol/l' ? 16.65 : 300;
            if (bzValues.length > 0) {
                var maxBZ = Math.max.apply(null, bzValues);
                if (bzUnit === 'mmol/l' && maxBZ > 16.65) rightAxisMax = 33.3;
                if (bzUnit !== 'mmol/l' && maxBZ > 300) rightAxisMax = 600;
            }
            var rightAxisStep = bzUnit === 'mmol/l'
                ? (rightAxisMax === 33.3 ? 3.33 : 1.665)
                : (rightAxisMax === 600 ? 60 : 30);

            // Punktsymbole je Parameter (v1: Kreis, Quadrat, Dreiecke, ...)
            var customPointStyles = {
                id: 'customPointStyles',
                afterDatasetsDraw: function (chart) {
                    var c = chart.ctx;
                    chart.data.datasets.forEach(function (dataset, datasetIndex) {
                        if (dataset.hidden) return;
                        var meta = chart.getDatasetMeta(datasetIndex);
                        meta.data.forEach(function (point, index) {
                            if (dataset.data[index] === null) return;
                            var x = point.x, y = point.y, size = 6;

                            c.save();
                            c.fillStyle = dataset.borderColor;
                            c.strokeStyle = dataset.borderColor;
                            c.lineWidth = 2;

                            switch (dataset.parameterKey) {
                                case 'spo2':
                                    c.beginPath();
                                    c.arc(x, y, size, 0, Math.PI * 2);
                                    c.fill();
                                    break;
                                case 'herzfreq':
                                    c.fillRect(x - size, y - size, size * 2, size * 2);
                                    break;
                                case 'rrsys':
                                    c.beginPath();
                                    c.moveTo(x, y - size);
                                    c.lineTo(x - size, y + size);
                                    c.lineTo(x + size, y + size);
                                    c.closePath();
                                    c.fill();
                                    break;
                                case 'rrdias':
                                    c.beginPath();
                                    c.moveTo(x, y + size);
                                    c.lineTo(x - size, y - size);
                                    c.lineTo(x + size, y - size);
                                    c.closePath();
                                    c.fill();
                                    break;
                                case 'atemfreq':
                                    c.beginPath();
                                    c.moveTo(x, y - size);
                                    c.lineTo(x + size, y);
                                    c.lineTo(x, y + size);
                                    c.lineTo(x - size, y);
                                    c.closePath();
                                    c.fill();
                                    break;
                                case 'temp':
                                    c.beginPath();
                                    c.arc(x, y, size, 0, Math.PI * 2);
                                    c.stroke();
                                    break;
                                case 'bz': {
                                    var spikes = 5;
                                    var outerRadius = size;
                                    var innerRadius = size / 2;
                                    c.beginPath();
                                    for (var i = 0; i < spikes * 2; i++) {
                                        var radius = i % 2 === 0 ? outerRadius : innerRadius;
                                        var angle = (Math.PI / spikes) * i - Math.PI / 2;
                                        var px = x + Math.cos(angle) * radius;
                                        var py = y + Math.sin(angle) * radius;
                                        if (i === 0) c.moveTo(px, py); else c.lineTo(px, py);
                                    }
                                    c.closePath();
                                    c.fill();
                                    break;
                                }
                                case 'etco2':
                                    c.beginPath();
                                    c.moveTo(x - size, y);
                                    c.lineTo(x + size, y);
                                    c.moveTo(x, y - size);
                                    c.lineTo(x, y + size);
                                    c.stroke();
                                    break;
                            }

                            c.restore();
                        });
                    });
                }
            };

            var chart = new Chart(canvas.getContext('2d'), {
                type: 'line',
                plugins: [customPointStyles],
                data: {
                    labels: chartLabels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                title: function (context) {
                                    return 'Zeit: ' + context[0].label;
                                },
                                label: function (context) {
                                    var value = context.parsed.y;
                                    var label = context.dataset.label;
                                    if (value === null || value === undefined) {
                                        return label + ': Kein Wert';
                                    }
                                    if (label.indexOf('SpO₂') !== -1) return label + ': ' + value.toFixed(1) + '%';
                                    if (label.indexOf('mmHg') !== -1) return label + ': ' + value.toFixed(0) + ' mmHg';
                                    if (label.indexOf('/min') !== -1) return label + ': ' + value.toFixed(0) + '/min';
                                    if (label.indexOf('°C') !== -1) return label + ': ' + value.toFixed(1) + '°C';
                                    if (label.indexOf('mg/dl') !== -1) return label + ': ' + value.toFixed(0) + ' mg/dl';
                                    if (label.indexOf('mmol/l') !== -1) return label + ': ' + value.toFixed(1) + ' mmol/l';
                                    return label + ': ' + value.toFixed(1);
                                }
                            },
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: 'white',
                            bodyColor: 'white',
                            borderColor: 'rgba(255, 255, 255, 0.3)',
                            borderWidth: 1
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: 'white' },
                            grid: { color: 'rgba(255,255,255,0.1)' }
                        },
                        y: {
                            type: 'linear',
                            position: 'left',
                            min: 0,
                            max: 100,
                            ticks: {
                                color: 'rgba(255, 255, 255, 1)',
                                font: { size: 10, weight: 'bold' },
                                stepSize: 10
                            },
                            grid: { color: 'rgba(75, 192, 192, 0.2)' },
                            title: {
                                display: true,
                                text: 'SpO₂ / AF / etCO₂ / Temp',
                                color: 'rgba(255, 255, 255, 1)',
                                font: { size: 12, weight: 'bold' }
                            }
                        },
                        y1: {
                            type: 'linear',
                            position: 'left',
                            min: 0,
                            max: rightAxisMax,
                            ticks: {
                                color: 'rgba(255, 255, 255, 1)',
                                font: { size: 10, weight: 'bold' },
                                stepSize: rightAxisStep
                            },
                            grid: { display: false },
                            title: {
                                display: true,
                                text: 'RR / HF / BZ',
                                color: 'rgba(255, 255, 255, 1)',
                                font: { size: 12, weight: 'bold' }
                            }
                        }
                    }
                }
            });

            canvas.style.height = '450px';

            // Legende mit v1-Symbolen, Klick blendet Datensätze aus
            var symbols = {
                spo2: '●', herzfreq: '■', rrsys: '▲', rrdias: '▼',
                atemfreq: '◆', temp: '○', bz: '★', etco2: '+'
            };
            var legendContainer = document.getElementById('legendToggle');
            if (legendContainer) {
                datasets.forEach(function (dataset, index) {
                    var legendItem = document.createElement('div');
                    legendItem.className = 'legend-item';
                    legendItem.addEventListener('click', function (e) {
                        e.stopPropagation(); // nicht zugleich den Chart-Klick auslösen
                        dataset.hidden = !dataset.hidden;
                        legendItem.classList.toggle('hidden', dataset.hidden);
                        chart.update();
                    });

                    var symbolSpan = document.createElement('span');
                    symbolSpan.style.color = dataset.borderColor;
                    symbolSpan.style.fontSize = '16px';
                    symbolSpan.style.marginRight = '8px';
                    symbolSpan.style.fontWeight = 'bold';
                    symbolSpan.textContent = symbols[dataset.parameterKey] || '●';

                    var label = document.createElement('span');
                    label.textContent = dataset.label;

                    legendItem.appendChild(symbolSpan);
                    legendItem.appendChild(label);
                    legendContainer.appendChild(legendItem);
                });
            }
        })();
    </script>

<?php elseif ($fokus === 'list'): ?>

    <!-- ── VERLAUF BEARBEITEN (v1 verlauf/list.php, Soft-Delete via v2-API) ── -->
    <?= $tabelleStyles ?>
    <div class="my-3" style="margin-left: 0">
        <div class="row mb-3" style="margin-left: 0">
            <div class="col">
                <div class="flex gap-2">
                    <a href="<?= $e($sectionUrl) ?>" class="ignis-btn ignis-btn--ghost">
                        <i class="fa-solid fa-arrow-left"></i> Zurück zur Übersicht
                    </a>
                    <?php if (!$istGesperrt): ?>
                        <a href="<?= $e($fokusUrl('add')) ?>" class="ignis-btn ignis-btn--ghost">
                            <i class="fa-solid fa-plus"></i> Neue Werte hinzufügen
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($istGesperrt): ?>
            <div class="row mb-3" style="margin-left: 0">
                <div class="col">
                    <div class="ignis-alert ignis-alert--warn">
                        <i class="fa-solid fa-lock"></i> <strong>Hinweis:</strong> Diese Dokumentation ist freigegeben und kann nicht mehr bearbeitet werden.
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($gruppen !== []): ?>
            <div class="stats-row">
                <span class="stat-item">
                    <i class="fa-regular fa-clock"></i> <?= $anzahlZeiten ?> Zeitpunkte
                </span>
                <span class="stat-item">
                    <i class="fa-solid fa-heart-pulse"></i> <?= $totalWerte ?> Einzelwerte
                </span>
            </div>
        <?php endif; ?>

        <div class="row" style="margin-left: 0">
            <div class="col">
                <?php if ($gruppen === []): ?>
                    <div class="no-data-compact">
                        <i class="fa-solid fa-circle-info" style="font-size: 32px; margin-bottom: 10px;"></i>
                        <h6>Noch keine Vitalparameter erfasst</h6>
                        <p class="mb-0" style="font-size: 13px;">
                            <?php if (!$istGesperrt): ?>
                                Klicken Sie auf "Neue Werte hinzufügen", um die ersten Vitalparameter zu dokumentieren.
                            <?php else: ?>
                                Für diese Dokumentation wurden keine Vitalparameter erfasst.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <?php
                    // v1 list.php sortiert absteigend (neueste zuerst)
                    $gruppenDesc = array_reverse($gruppen, true);
                    $renderTabelle($gruppenDesc, !$istGesperrt);
                    ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!$istGesperrt): ?>
    <script>
        // Einzelwert-Löschung: Soft-Delete über die v2-API (v1: GET-Action)
        (function () {
            'use strict';
            var DELETE_URL = <?= json_encode(EnotfUrl::api('vitals/delete')) ?>;
            var ENR = <?= json_encode($enr, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

            document.querySelectorAll('[data-vital-delete]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var frage = "Parameter '" + btn.dataset.vitalLabel + "' löschen?";
                    var entscheidung = window.Dialog
                        ? window.Dialog.confirm(frage, { danger: true, confirmText: 'Löschen', title: 'Parameter löschen' })
                        : Promise.resolve(window.confirm(frage));
                    entscheidung.then(function (ok) {
                        if (!ok) return;
                        fetch(DELETE_URL, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({ enr: ENR, id: parseInt(btn.dataset.vitalDelete, 10) })
                        })
                            .then(function (r) { return r.json().catch(function () { return {}; }); })
                            .then(function (data) {
                                if (data && data.ok) {
                                    window.location.reload();
                                } else if (window.Dialog) {
                                    window.Dialog.alert((data && data.error) || 'Löschen fehlgeschlagen', { type: 'error' });
                                }
                            })
                            .catch(function () {
                                if (window.Dialog) window.Dialog.alert('Netzwerkfehler beim Löschen', { type: 'error' });
                            });
                    });
                });
            });
        })();
    </script>
    <?php endif; ?>

<?php else: ?>

    <!-- ── WERTE HINZUFÜGEN (v1 verlauf/add.php: Keypad + Range-Strip) ── -->
    <form id="vitalsForm" method="post" action="" onsubmit="return false">
        <input type="hidden" id="zeitpunkt" value="<?= date('Y-m-d\TH:i') ?>" data-autosave-ignore>
        <div class="row">
            <div class="col position-relative">
                <div class="row my-3">
                    <div class="col edivi__vitalparam-box" data-before="SpO₂" data-after="%">
                        <input type="text" name="spo2" id="spo2" class="form-control edivi__vitalparam keypad-input" placeholder="96" data-autosave-ignore>
                    </div>
                    <div class="col edivi__vitalparam-box" data-before="AF" data-after="/min">
                        <input type="text" name="atemfreq" id="atemfreq" class="form-control edivi__vitalparam keypad-input" placeholder="16" data-autosave-ignore>
                    </div>
                    <div class="col edivi__vitalparam-box" data-before="etCO₂" data-after="mmHg">
                        <input type="text" name="etco2" id="etco2" class="form-control edivi__vitalparam keypad-input" placeholder="35" data-autosave-ignore>
                    </div>
                </div>
                <div class="row my-3">
                    <div class="col edivi__vitalparam-box" data-before="HF" data-after="/min">
                        <input type="text" name="herzfreq" id="herzfreq" class="form-control edivi__vitalparam keypad-input" placeholder="80" data-autosave-ignore>
                    </div>
                    <div class="col edivi__vitalparam-box" data-before="NIBP/RR" data-after="mmHg">
                        <input type="text" name="rrsys" id="rrsys" class="form-control edivi__vitalparam-shared keypad-input" placeholder="120" data-autosave-ignore>
                        <div class="edivi_vitalparam-spacer">/</div>
                        <input type="text" name="rrdias" id="rrdias" class="form-control edivi__vitalparam-shared keypad-input" placeholder="80" data-autosave-ignore>
                    </div>
                </div>
                <div class="row my-3">
                    <div class="col edivi__vitalparam-box" data-before="BZ" data-after="<?= $e($bzUnit) ?>">
                        <input type="text" name="bz" id="bz" class="form-control edivi__vitalparam keypad-input"
                               placeholder="<?= $bzUnit === 'mmol/l' ? '5,0' : '90' ?>" data-autosave-ignore>
                    </div>
                    <div class="col edivi__vitalparam-box" data-before="Temperatur" data-after="°C">
                        <input type="text" name="temp" id="temp" class="form-control edivi__vitalparam keypad-input" placeholder="36,5" data-autosave-ignore>
                    </div>
                </div>
                <div class="row edivi__vitalparam-mainbuttons">
                    <div class="col">
                        <a href="<?= $e($sectionUrl) ?>">Abbrechen</a>
                    </div>
                    <div class="col" style="border-left: 2px solid #191919;">
                        <button type="button" id="saveVitalsBtn">Speichern</button>
                    </div>
                </div>
            </div>
            <div class="col-5">
                <!-- Range Strip (Normbereichs-Färbung des aktiven Felds) -->
                <div class="range-strip-wrapper">
                    <div class="range-strip-container">
                        <div class="range-strip" id="rangeStrip"></div>
                    </div>
                </div>
                <!-- Keypad -->
                <div class="keypad-container">
                    <div class="keypad-grid">
                        <?php foreach (['7', '8', '9', '4', '5', '6', '1', '2', '3'] as $d): ?>
                            <button type="button" class="keypad-btn" data-keypad-digit="<?= $d ?>"><?= $d ?></button>
                        <?php endforeach; ?>
                        <button type="button" class="keypad-btn wide" data-keypad-digit="0">0</button>
                        <button type="button" class="keypad-btn special" data-keypad-digit=",">,</button>
                    </div>
                    <div class="function-grid">
                        <button type="button" class="keypad-btn danger" data-keypad-clear>Löschen</button>
                        <button type="button" class="keypad-btn danger" data-keypad-backspace><i class="fa-solid fa-delete-left"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <script src="<?= $e(asset('plugins/enotf/assets/vitals-keypad.js')) ?>"></script>
    <script>
        // Keypad, Färbung und Range-Strip kommen aus vitals-keypad.js,
        // gespeichert wird als EIN Request an die v2-Vitals-API.
        (function () {
            'use strict';
            var VITALS_API = <?= json_encode(EnotfUrl::api('vitals')) ?>;
            var ENR = <?= json_encode($enr, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
            var INDEX_URL = <?= json_encode($sectionUrl) ?>;
            var FIELDS = ['spo2', 'atemfreq', 'etco2', 'herzfreq', 'rrsys', 'rrdias', 'bz', 'temp'];

            window.EnotfV2VitalsKeypad.init({ bzUnit: <?= json_encode($bzUnit) ?> });

            // ── Speichern: EIN Request an die v2-Vitals-API ──────────
            var saveBtn = document.getElementById('saveVitalsBtn');
            if (saveBtn) saveBtn.addEventListener('click', function () {
                var werte = {};
                var anzahl = 0;
                FIELDS.forEach(function (name) {
                    var input = document.getElementsByName(name)[0];
                    if (!input) return;
                    var wert = input.value.trim();
                    if (wert === '') return;
                    werte[name] = wert; // BZ in Anzeige-Einheit, die API konvertiert
                    anzahl++;
                });
                if (anzahl === 0) {
                    if (window.Dialog) window.Dialog.alert('Keine Werte zum Speichern eingetragen.', { type: 'warning' });
                    return;
                }

                saveBtn.disabled = true;
                fetch(VITALS_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        enr: ENR,
                        zeitpunkt: document.getElementById('zeitpunkt').value,
                        werte: werte
                    })
                })
                    .then(function (r) { return r.json().catch(function () { return {}; }); })
                    .then(function (data) {
                        if (data && data.ok) {
                            window.location.href = INDEX_URL;
                            return;
                        }
                        saveBtn.disabled = false;
                        if (window.Dialog) window.Dialog.alert((data && data.error) || 'Speichern fehlgeschlagen', { type: 'error' });
                    })
                    .catch(function () {
                        saveBtn.disabled = false;
                        if (window.Dialog) window.Dialog.alert('Netzwerkfehler beim Speichern', { type: 'error' });
                    });
            });
        })();
    </script>

<?php endif; ?>
