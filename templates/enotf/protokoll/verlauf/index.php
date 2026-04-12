<?php
/**
 * View: enotf/protokoll/verlauf/index.php
 *
 * @var \PDO $pdo
 */


use App\Auth\Permissions;
use App\Helpers\BloodSugarHelper;

$daten = array();
$vitals = array();

// Initialize BloodSugarHelper
$bzHelper = new BloodSugarHelper($pdo);
$bzUnit = $bzHelper->getCurrentUnit();
$bzDecimalPlaces = $bzHelper->getDecimalPlaces();

if (isset($_GET['enr'])) {
    // Basis-Daten laden
    $queryget = "SELECT * FROM intra_edivi WHERE enr = :enr";
    $stmt = $pdo->prepare($queryget);
    $stmt->execute(['enr' => $_GET['enr']]);
    $daten = $stmt->fetch(PDO::FETCH_ASSOC);

    if (count($daten) == 0) {
        header("Location: " . BASE_PATH . "enotf/");
        exit();
    }

    // GEÄNDERT: Einzelwerte aus neuer Tabelle laden (nur aktive)
    $queryVitals = "SELECT * FROM intra_edivi_vitalparameter_einzelwerte 
                    WHERE enr = :enr AND geloescht = 0 
                    ORDER BY zeitpunkt ASC, parameter_name ASC";
    $stmtVitals = $pdo->prepare($queryVitals);
    $stmtVitals->execute(['enr' => $_GET['enr']]);
    $vitalsRaw = $stmtVitals->fetchAll(PDO::FETCH_ASSOC);

    // GEÄNDERT: Daten für Chart umstrukturieren
    $vitals = [];
    $groupedByTime = [];

    // Nach Zeitpunkt gruppieren
    foreach ($vitalsRaw as $vital) {
        $zeitpunkt = $vital['zeitpunkt'];
        if (!isset($groupedByTime[$zeitpunkt])) {
            $groupedByTime[$zeitpunkt] = [
                'zeitpunkt' => $zeitpunkt,
                'spo2' => null,
                'atemfreq' => null,
                'etco2' => null,
                'rrsys' => null,
                'rrdias' => null,
                'herzfreq' => null,
                'bz' => null,
                'temp' => null
            ];
        }

        // Parameter-Namen zu Feld-Namen mapping
        $parameterMapping = [
            'SpO₂' => 'spo2',
            'Atemfrequenz' => 'atemfreq',
            'etCO₂' => 'etco2',
            'RR systolisch' => 'rrsys',
            'RR diastolisch' => 'rrdias',
            'Herzfrequenz' => 'herzfreq',
            'Blutzucker' => 'bz',
            'Temperatur' => 'temp'
        ];

        if (isset($parameterMapping[$vital['parameter_name'] ?? ''])) {
            $fieldName = $parameterMapping[$vital['parameter_name'] ?? ''];
            $groupedByTime[$zeitpunkt][$fieldName] = $vital['parameter_wert'];
        }
    }

    // Sortierte Zeitpunkte für Chart
    ksort($groupedByTime);
    $vitals = array_values($groupedByTime);
} else {
    header("Location: " . BASE_PATH . "enotf/");
    exit();
}

if ($daten['freigegeben'] == 1) {
    $ist_freigegeben = true;
} else {
    $ist_freigegeben = false;
}

$daten['last_edit'] = !empty($daten['last_edit']) ? (new DateTime($daten['last_edit']))->format('d.m.Y H:i') : NULL;
$enr = $daten['enr'];
$prot_url = "https://" . SYSTEM_URL . "/enotf/prot/index.php?enr=" . $enr;

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';

// JSON-Daten für Chart.js vorbereiten
$chartLabels = [];
$chartSpo2 = [];
$chartRRSys = [];
$chartRRDias = [];
$chartHerzfreq = [];
$chartAtemfreq = [];
$chartTemp = [];
$chartEtco2 = [];
$chartBz = [];

foreach ($vitals as $vital) {
    $zeitpunkt = new DateTime($vital['zeitpunkt']);
    $chartLabels[] = $zeitpunkt->format('H:i');
    $chartSpo2[] = $vital['spo2'] ? floatval(str_replace(',', '.', $vital['spo2'])) : null;
    $chartRRSys[] = $vital['rrsys'] ? floatval(str_replace(',', '.', $vital['rrsys'])) : null;
    $chartRRDias[] = $vital['rrdias'] ? floatval(str_replace(',', '.', $vital['rrdias'])) : null;
    $chartHerzfreq[] = $vital['herzfreq'] ? floatval(str_replace(',', '.', $vital['herzfreq'])) : null;
    $chartAtemfreq[] = $vital['atemfreq'] ? floatval(str_replace(',', '.', $vital['atemfreq'])) : null;
    $chartTemp[] = $vital['temp'] ? floatval(str_replace(',', '.', $vital['temp'])) : null;
    $chartEtco2[] = $vital['etco2'] ? floatval(str_replace(',', '.', $vital['etco2'])) : null;
    // Blutzucker: Werte sind in mg/dl gespeichert, konvertiere zur Anzeige-Einheit
    $chartBz[] = $vital['bz'] ? $bzHelper->toDisplayUnit($vital['bz']) : null;
}

// GEÄNDERT: Anzahl der verfügbaren Einzelwerte ermitteln
$queryCount = "SELECT COUNT(*) as count FROM intra_edivi_vitalparameter_einzelwerte WHERE enr = :enr AND geloescht = 0";
$stmtCount = $pdo->prepare($queryCount);
$stmtCount->execute(['enr' => $enr]);
$totalVitals = $stmtCount->fetch(PDO::FETCH_ASSOC)['count'];
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = "[#" . $daten['enr'] . "] &rsaquo; eNOTF";
    include __DIR__ . '/../../../../assets/components/enotf/_head.php';
    ?>
    <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

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

        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .vitals-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .vitals-stat {
            display: inline-block;
            background: rgba(0, 123, 255, 0.2);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            margin-right: 10px;
            font-size: 14px;
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="verlauf" data-session-token="<?= $_SESSION['enotf_session_token'] ?? '' ?>" data-base-path="<?= BASE_PATH ?>" data-pin-enabled="<?= $pinEnabled ?>">
    <?php include __DIR__ . '/../../../../assets/components/enotf/topbar.php'; ?>

    <div class="container-fluid" id="edivi__container">
        <div class="row h-100">
            <?php include __DIR__ . '/../../../../assets/components/enotf/nav.php'; ?>
            <div class="col-12 col-md-11" id="edivi__content">
                <div class="row my-3">
                    <div class="col">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex gap-2">
                                <a href="list.php?enr=<?= $enr ?>&action=manage" class="btn btn-outline-light">
                                    <i class="fa-solid fa-list"></i> Verlauf bearbeiten
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col">
                        <div class="vitals-info">
                            <h6 class="text-light mb-2">
                                <i class="fa-solid fa-chart-line"></i> Vitalparameter-Übersicht
                            </h6>
                            <div>
                                <span class="vitals-stat">
                                    <i class="fa-solid fa-database"></i> <?= $totalVitals ?> Einzelwerte erfasst
                                </span>
                                <span class="vitals-stat">
                                    <i class="fa-regular fa-clock"></i> <?= count($vitals) ?> Zeitpunkte dokumentiert
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kombinierter Chart -->
                <div class="row">
                    <div class="col">
                        <div class="row edivi__box">
                            <h5 class="text-light px-2 py-1">Alle Vitalparameter</h5>
                            <div class="col p-3">
                                <div class="legend-toggle" id="legendToggle">
                                    <!-- Wird durch JavaScript gefüllt -->
                                </div>
                                <div class="chart-container position-relative" onclick="addValues()">
                                    <div class="chart-click-hint">
                                        <i class="fa-solid fa-plus"></i> Klicken zum Hinzufügen
                                    </div>
                                    <canvas id="chartCombined" width="400" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (empty($vitals)): ?>
                    <div class="row mt-3">
                        <div class="col text-center">
                            <div class="alert alert-info">
                                <h5><i class="fa-solid fa-circle-info"></i> Noch keine Vitalparameter dokumentiert</h5>
                                <p>Klicken Sie auf "Werte hinzufügen" oder auf den Chart-Bereich, um die ersten Vitalparameter zu erfassen.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    include __DIR__ . '/../../../../assets/functions/enotf/notify.php';
    include __DIR__ . '/../../../../assets/functions/enotf/field_checks.php';
    include __DIR__ . '/../../../../assets/functions/enotf/clock.php';
    ?>

    <script>
        const chartLabels = <?= json_encode($chartLabels) ?>;
        const chartData = {
            spo2: <?= json_encode($chartSpo2) ?>,
            rrsys: <?= json_encode($chartRRSys) ?>,
            rrdias: <?= json_encode($chartRRDias) ?>,
            herzfreq: <?= json_encode($chartHerzfreq) ?>,
            atemfreq: <?= json_encode($chartAtemfreq) ?>,
            temp: <?= json_encode($chartTemp) ?>,
            etco2: <?= json_encode($chartEtco2) ?>,
            bz: <?= json_encode($chartBz) ?>
        };

        console.log('Chart Labels:', chartLabels);
        console.log('Chart Data:', chartData);
        console.log('Total Data Points:', chartLabels.length);

        function parseValue(value) {
            if (value === null || value === undefined || value === '') return null;
            const parsed = parseFloat(String(value).replace(',', '.'));
            return isNaN(parsed) ? null : parsed;
        }

        const numericData = {
            spo2: chartData.spo2.map(parseValue),
            rrsys: chartData.rrsys.map(parseValue),
            rrdias: chartData.rrdias.map(parseValue),
            herzfreq: chartData.herzfreq.map(parseValue),
            atemfreq: chartData.atemfreq.map(parseValue),
            temp: chartData.temp.map(parseValue),
            etco2: chartData.etco2.map(parseValue),
            bz: chartData.bz.map(parseValue)
        };

        const parameterConfig = {
            rrsys: {
                axis: 'y1',
                color: 'rgb(255, 99, 132)',
                label: 'RR systolisch (mmHg)',
                category: 'Hohe Werte'
            },
            rrdias: {
                axis: 'y1',
                color: 'rgb(54, 162, 235)',
                label: 'RR diastolisch (mmHg)',
                category: 'Hohe Werte'
            },
            herzfreq: {
                axis: 'y1',
                color: 'rgb(255, 205, 86)',
                label: 'Herzfrequenz (/min)',
                category: 'Hohe Werte'
            },
            bz: {
                axis: 'y1',
                color: 'rgb(83, 102, 255)',
                label: 'Blutzucker (<?= $bzUnit ?>)',
                category: 'Hohe Werte'
            },
            etco2: {
                axis: 'y',
                color: 'rgb(199, 199, 199)',
                label: 'etCO₂ (mmHg)',
                category: 'Niedrige Werte'
            },
            spo2: {
                axis: 'y',
                color: 'rgb(75, 192, 192)',
                label: 'SpO₂ (%)',
                category: 'Niedrige Werte'
            },
            atemfreq: {
                axis: 'y',
                color: 'rgb(153, 102, 255)',
                label: 'Atemfrequenz (/min)',
                category: 'Niedrige Werte'
            },
            temp: {
                axis: 'y',
                color: 'rgb(255, 159, 64)',
                label: 'Temperatur (°C)',
                category: 'Niedrige Werte'
            }
        };

        const datasets = [];
        Object.keys(parameterConfig).forEach(paramKey => {
            const config = parameterConfig[paramKey];
            const hasData = numericData[paramKey].some(v => v !== null && v !== undefined);

            if (hasData) {
                datasets.push({
                    label: config.label,
                    data: numericData[paramKey],
                    borderColor: config.color,
                    backgroundColor: config.color.replace('rgb', 'rgba').replace(')', ', 0.1)'),
                    tension: 0.4,
                    yAxisID: config.axis,
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    pointBackgroundColor: 'transparent',
                    pointBorderColor: 'transparent',
                    pointBorderWidth: 0,
                    borderWidth: 3,
                    hidden: false,
                    spanGaps: true,
                    parameterKey: paramKey,
                    category: config.category
                });
            }
        });

        function calculateRightAxisMax() {
            const bzValues = numericData.bz.filter(v => v !== null && v !== undefined);
            const bzUnit = <?= json_encode($bzUnit) ?>;

            if (bzValues.length === 0) {
                return bzUnit === 'mmol/l' ? 16.65 : 300;
            }

            const maxBZ = Math.max(...bzValues);
            if (bzUnit === 'mmol/l') {
                // mmol/l Bereich: Standard 16.65 (entspricht 300 mg/dl), erweitert auf 33.3 (entspricht 600 mg/dl)
                if (maxBZ > 16.65) {
                    console.log(`Blutzucker-Maximum: ${maxBZ} mmol/l - Skala wird auf 33.3 erweitert`);
                    return 33.3;
                }
                return 16.65;
            } else {
                // mg/dl Bereich
                if (maxBZ > 300) {
                    console.log(`Blutzucker-Maximum: ${maxBZ} mg/dl - Skala wird auf 600 erweitert`);
                    return 600;
                }
                return 300;
            }
        }

        const rightAxisMax = calculateRightAxisMax();
        const bzUnit = <?= json_encode($bzUnit) ?>;
        const rightAxisStep = bzUnit === 'mmol/l' ?
            (rightAxisMax === 33.3 ? 3.33 : 1.665) :
            (rightAxisMax === 600 ? 60 : 30);

        const customPointStyles = {
            id: 'customPointStyles',
            afterDatasetsDraw(chart) {
                const ctx = chart.ctx;

                chart.data.datasets.forEach((dataset, datasetIndex) => {
                    if (dataset.hidden) return;

                    const meta = chart.getDatasetMeta(datasetIndex);

                    meta.data.forEach((point, index) => {
                        if (dataset.data[index] === null) return;

                        const x = point.x;
                        const y = point.y;
                        const size = 6;

                        ctx.save();
                        ctx.fillStyle = dataset.borderColor;
                        ctx.strokeStyle = dataset.borderColor;
                        ctx.lineWidth = 2;

                        switch (dataset.parameterKey) {
                            case 'spo2':
                                ctx.beginPath();
                                ctx.arc(x, y, size, 0, Math.PI * 2);
                                ctx.fill();
                                break;

                            case 'herzfreq':
                                ctx.fillRect(x - size, y - size, size * 2, size * 2);
                                break;

                            case 'rrsys':
                                ctx.beginPath();
                                ctx.moveTo(x, y - size);
                                ctx.lineTo(x - size, y + size);
                                ctx.lineTo(x + size, y + size);
                                ctx.closePath();
                                ctx.fill();
                                break;

                            case 'rrdias':
                                ctx.beginPath();
                                ctx.moveTo(x, y + size);
                                ctx.lineTo(x - size, y - size);
                                ctx.lineTo(x + size, y - size);
                                ctx.closePath();
                                ctx.fill();
                                break;

                            case 'atemfreq':
                                ctx.beginPath();
                                ctx.moveTo(x, y - size);
                                ctx.lineTo(x + size, y);
                                ctx.lineTo(x, y + size);
                                ctx.lineTo(x - size, y);
                                ctx.closePath();
                                ctx.fill();
                                break;

                            case 'temp':
                                ctx.beginPath();
                                ctx.arc(x, y, size, 0, Math.PI * 2);
                                ctx.stroke();
                                break;

                            case 'bz':
                                const spikes = 5;
                                const outerRadius = size;
                                const innerRadius = size / 2;

                                ctx.beginPath();
                                for (let i = 0; i < spikes * 2; i++) {
                                    const radius = i % 2 === 0 ? outerRadius : innerRadius;
                                    const angle = (Math.PI / spikes) * i - Math.PI / 2;
                                    const px = x + Math.cos(angle) * radius;
                                    const py = y + Math.sin(angle) * radius;

                                    if (i === 0) {
                                        ctx.moveTo(px, py);
                                    } else {
                                        ctx.lineTo(px, py);
                                    }
                                }
                                ctx.closePath();
                                ctx.fill();
                                break;

                            case 'etco2':
                                ctx.beginPath();
                                ctx.moveTo(x - size, y);
                                ctx.lineTo(x + size, y);
                                ctx.moveTo(x, y - size);
                                ctx.lineTo(x, y + size);
                                ctx.stroke();
                                break;
                        }

                        ctx.restore();
                    });
                });
            }
        };

        const ctx = document.getElementById('chartCombined').getContext('2d');
        const chart = new Chart(ctx, {
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
                            title: function(context) {
                                return 'Zeit: ' + context[0].label;
                            },
                            label: function(context) {
                                const value = context.parsed.y;
                                const label = context.dataset.label;

                                if (value !== null && value !== undefined) {
                                    if (label.includes('SpO₂')) {
                                        return `${label}: ${value.toFixed(1)}%`;
                                    } else if (label.includes('mmHg')) {
                                        return `${label}: ${value.toFixed(0)} mmHg`;
                                    } else if (label.includes('/min')) {
                                        return `${label}: ${value.toFixed(0)}/min`;
                                    } else if (label.includes('°C')) {
                                        return `${label}: ${value.toFixed(1)}°C`;
                                    } else if (label.includes('mg/dl')) {
                                        return `${label}: ${value.toFixed(0)} mg/dl`;
                                    } else if (label.includes('mmol/l')) {
                                        return `${label}: ${value.toFixed(1)} mmol/l`;
                                    } else {
                                        return `${label}: ${value.toFixed(1)}`;
                                    }
                                }
                                return label + ': Kein Wert';
                            },
                            footer: function(tooltipItems) {
                                const item = tooltipItems[0];
                                if (item) {
                                    const dataset = item.dataset;
                                    const rightAxisText = rightAxisMax === 600 ? '0-600 (erweitert für BZ)' : '0-300';
                                    return `Achse: ${dataset.category} (${dataset.yAxisID === 'y' ? '0-100' : rightAxisText})`;
                                }
                                return '';
                            }
                        },
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        footerColor: 'rgba(255, 255, 255, 0.7)',
                        borderColor: 'rgba(255, 255, 255, 0.3)',
                        borderWidth: 1
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            color: 'white'
                        },
                        grid: {
                            color: 'rgba(255,255,255,0.1)'
                        }
                    },
                    y: {
                        type: 'linear',
                        position: 'left',
                        min: 0,
                        max: 100,
                        ticks: {
                            color: 'rgba(255, 255, 255, 1)',
                            font: {
                                size: 10,
                                weight: 'bold'
                            },
                            stepSize: 10
                        },
                        grid: {
                            color: 'rgba(75, 192, 192, 0.2)'
                        },
                        title: {
                            display: true,
                            text: 'SpO₂ / AF / etCO₂ / Temp',
                            color: 'rgba(255, 255, 255, 1)',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        position: 'left',
                        min: 0,
                        max: rightAxisMax,
                        ticks: {
                            color: 'rgba(255, 255, 255, 1)',
                            font: {
                                size: 10,
                                weight: 'bold'
                            },
                            stepSize: rightAxisStep
                        },
                        grid: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: rightAxisMax === 600 ?
                                'RR / HF / BZ' : 'RR / HF / BZ',
                            color: 'rgba(255, 255, 255, 1)',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    }
                }
            }
        });

        function createLegend() {
            const legendContainer = document.getElementById('legendToggle');
            legendContainer.innerHTML = '';

            const symbols = {
                'spo2': '●',
                'herzfreq': '■',
                'rrsys': '▲',
                'rrdias': '▼',
                'atemfreq': '◆',
                'temp': '○',
                'bz': '★',
                'etco2': '+'
            };

            datasets.forEach((dataset, index) => {
                const legendItem = document.createElement('div');
                legendItem.className = 'legend-item';
                legendItem.onclick = () => toggleDataset(index);

                const symbolSpan = document.createElement('span');
                symbolSpan.style.color = dataset.borderColor;
                symbolSpan.style.fontSize = '16px';
                symbolSpan.style.marginRight = '8px';
                symbolSpan.style.fontWeight = 'bold';
                symbolSpan.textContent = symbols[dataset.parameterKey] || '●';

                const label = document.createElement('span');
                label.textContent = dataset.label;

                legendItem.appendChild(symbolSpan);
                legendItem.appendChild(label);
                legendContainer.appendChild(legendItem);
            });
        }

        function toggleDataset(index) {
            const dataset = chart.data.datasets[index];
            dataset.hidden = !dataset.hidden;

            const legendItems = document.querySelectorAll('.legend-item');
            if (legendItems[index]) {
                legendItems[index].classList.toggle('hidden', dataset.hidden);
            }

            chart.update();
        }

        function addValues() {
            <?php if (!$ist_freigegeben): ?>
                window.location.href = 'add.php?enr=<?= $enr ?>';
            <?php else: ?>
                showAlert('Diese Dokumentation ist bereits freigegeben und kann nicht mehr bearbeitet werden.', {
                    type: 'warning',
                    title: 'Nicht bearbeitbar'
                });
            <?php endif; ?>
        }

        document.getElementById('chartCombined').style.height = '450px';

        createLegend();

        const infoText = document.createElement('div');
        const chartContainer = document.querySelector('.chart-container').parentNode;
        chartContainer.insertBefore(infoText, chartContainer.firstChild);
    </script>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>