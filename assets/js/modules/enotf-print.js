/**
 * enotf-print.js — JS-Logik fuer templates/enotf/print/index.php
 *
 * Drei Bereiche, die vorher inline im Template lebten:
 *
 *   1) Vitalzeichen-Chart (Chart.js)
 *      Wird vom Template aufgerufen mit serverseitig gerenderten Daten:
 *
 *        initEnotfPrintVitalsChart({
 *            chartLabels: <?= json_encode($chartLabels) ?>,
 *            chartData:   <?= json_encode($chartData) ?>,
 *            bzUnit:      <?= json_encode($bzUnit) ?>,
 *        });
 *
 *      Erstellt nur ein Diagramm wenn #vitalChart vorhanden ist.
 *
 *   2) Auto-Berechnung des Patientenalters (Self-Init)
 *      Liest #patgebdat[data-date], schreibt in #_AGE_.
 *
 *   3) Zoom + Print-Handling (Self-Init)
 *      currentZoom-State + window.zoomIn/zoomOut, beforeprint/afterprint Hooks.
 *
 * Self-Init via DOMContentLoaded.
 */
(function (global) {
    'use strict';

    // ── 1) Vitalwerte-Chart (Opt-In via Template-Call) ─────────────
    global.initEnotfPrintVitalsChart = function (opts) {
        opts = opts || {};
        const chartLabels = opts.chartLabels || [];
        const chartData   = opts.chartData   || {};
        const bzUnit      = opts.bzUnit      || 'mg/dl';

        global.addEventListener('load', function () {
            const ctx = document.getElementById('vitalChart');
            if (!ctx) return;

            const customPointStyles = {
                id: 'customPointStyles',
                afterDatasetsDraw(chart) {
                    const c = chart.ctx;
                    chart.data.datasets.forEach((dataset, datasetIndex) => {
                        const meta = chart.getDatasetMeta(datasetIndex);
                        meta.data.forEach((point, index) => {
                            if (dataset.data[index] === null) return;
                            const x = point.x, y = point.y, size = 6;

                            c.save();
                            c.fillStyle = 'black';
                            c.strokeStyle = 'black';
                            c.lineWidth = 2;

                            switch (dataset.label) {
                                case 'SpO₂':
                                    c.beginPath();
                                    c.arc(x, y, size, 0, Math.PI * 2);
                                    c.fill();
                                    break;
                                case 'HF':
                                    c.fillRect(x - size, y - size, size * 2, size * 2);
                                    break;
                                case 'RRsys':
                                    c.beginPath();
                                    c.moveTo(x, y - size);
                                    c.lineTo(x - size, y + size);
                                    c.lineTo(x + size, y + size);
                                    c.closePath();
                                    c.fill();
                                    break;
                                case 'RRdia':
                                    c.beginPath();
                                    c.moveTo(x, y + size);
                                    c.lineTo(x - size, y - size);
                                    c.lineTo(x + size, y - size);
                                    c.closePath();
                                    c.fill();
                                    break;
                                case 'AF':
                                    c.beginPath();
                                    c.moveTo(x, y - size);
                                    c.lineTo(x + size, y);
                                    c.lineTo(x, y + size);
                                    c.lineTo(x - size, y);
                                    c.closePath();
                                    c.fill();
                                    break;
                                case 'Temp':
                                    c.beginPath();
                                    c.arc(x, y, size, 0, Math.PI * 2);
                                    c.stroke();
                                    break;
                                case 'BZ': {
                                    const spikes = 5;
                                    const outerRadius = size;
                                    const innerRadius = size / 2;
                                    c.beginPath();
                                    for (let i = 0; i < spikes * 2; i++) {
                                        const radius = i % 2 === 0 ? outerRadius : innerRadius;
                                        const angle = (Math.PI / spikes) * i - Math.PI / 2;
                                        const px = x + Math.cos(angle) * radius;
                                        const py = y + Math.sin(angle) * radius;
                                        if (i === 0) c.moveTo(px, py); else c.lineTo(px, py);
                                    }
                                    c.closePath();
                                    c.fill();
                                    break;
                                }
                                case 'etCO₂':
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
                },
            };

            const y1Max      = bzUnit === 'mmol/l' ? 16.65 : 300;
            const y1Step     = bzUnit === 'mmol/l' ? 1.665 : 30;
            const y1Title    = 'RR / HF / BZ (0-' + (bzUnit === 'mmol/l' ? '16.65' : '300') + ')';

            new Chart(ctx, {
                type: 'line',
                plugins: [customPointStyles],
                data: {
                    labels: chartLabels,
                    datasets: [
                        { label: 'SpO₂',  data: chartData.spo2,     borderColor: 'black', backgroundColor: 'transparent', borderWidth: 2,   borderDash: [],            yAxisID: 'y',  tension: 0.3, pointStyle: false, spanGaps: true },
                        { label: 'HF',    data: chartData.herzfreq, borderColor: 'black', backgroundColor: 'transparent', borderWidth: 2,   borderDash: [5, 5],        yAxisID: 'y1', tension: 0.3, pointStyle: false, spanGaps: true },
                        { label: 'RRsys', data: chartData.rrsys,    borderColor: 'black', backgroundColor: 'transparent', borderWidth: 2,   borderDash: [10, 2, 2, 2], yAxisID: 'y1', tension: 0.3, pointStyle: false, spanGaps: true },
                        { label: 'RRdia', data: chartData.rrdias,   borderColor: 'black', backgroundColor: 'transparent', borderWidth: 2,   borderDash: [2, 2],        yAxisID: 'y1', tension: 0.3, pointStyle: false, spanGaps: true },
                        { label: 'AF',    data: chartData.atemfreq, borderColor: 'black', backgroundColor: 'transparent', borderWidth: 1.5, borderDash: [],            yAxisID: 'y',  tension: 0.3, pointStyle: false, spanGaps: true },
                        { label: 'Temp',  data: chartData.temp,     borderColor: 'black', backgroundColor: 'transparent', borderWidth: 2.5, borderDash: [8, 4],        yAxisID: 'y',  tension: 0.3, pointStyle: false, spanGaps: true },
                        { label: 'BZ',    data: chartData.bz,       borderColor: 'black', backgroundColor: 'transparent', borderWidth: 2,   borderDash: [15, 3, 3, 3], yAxisID: 'y1', tension: 0.3, pointStyle: false, spanGaps: true },
                        { label: 'etCO₂', data: chartData.etco2,    borderColor: 'black', backgroundColor: 'transparent', borderWidth: 1.5, borderDash: [3, 3],        yAxisID: 'y',  tension: 0.3, pointStyle: false, spanGaps: true },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 1.75,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                color: 'black',
                                font: { size: 9 },
                                padding: 15,
                                boxWidth: 40,
                                boxHeight: 2,
                                usePointStyle: false,
                                generateLabels: function (chart) {
                                    const datasets = chart.data.datasets;
                                    return datasets.map((dataset, i) => {
                                        const symbols = { 'SpO₂': '●', 'HF': '■', 'RRsys': '▲', 'RRdia': '▼', 'AF': '◆', 'Temp': '○', 'BZ': '★', 'etCO₂': '+' };
                                        const units   = { 'SpO₂': '%', 'HF': '/min', 'RRsys': 'mmHg', 'RRdia': 'mmHg', 'AF': '/min', 'Temp': '°C', 'BZ': bzUnit, 'etCO₂': 'mmHg' };
                                        const label   = dataset.label;
                                        const symbol  = symbols[label] || '●';
                                        const unit    = units[label]   || '';
                                        return {
                                            text: `${symbol} ${label} (${unit})`,
                                            fillStyle:   'transparent',
                                            strokeStyle: 'black',
                                            lineWidth:   dataset.borderWidth || 2,
                                            lineDash:    dataset.borderDash  || [],
                                            hidden:      false,
                                            index:       i,
                                        };
                                    });
                                },
                            },
                        },
                        tooltip: { enabled: false },
                    },
                    scales: {
                        x: {
                            ticks: { color: 'black', font: { size: 9 } },
                            grid:  { color: 'rgba(0, 0, 0, 0.1)', drawBorder: true, borderColor: 'black', borderWidth: 2 },
                            title: { display: true, text: 'Zeit (Uhrzeit)', color: 'black', font: { size: 11, weight: 'bold' } },
                        },
                        y: {
                            type: 'linear', position: 'left', min: 0, max: 100,
                            ticks: { color: 'black', font: { size: 9, weight: 'bold' }, stepSize: 10 },
                            grid:  { color: 'rgba(0, 0, 0, 0.1)', drawBorder: true, borderColor: 'black', borderWidth: 2 },
                            title: { display: true, text: 'SpO₂ / AF / etCO₂ / Temp (0-100)', color: 'black', font: { size: 10, weight: 'bold' } },
                        },
                        y1: {
                            type: 'linear', position: 'right', min: 0, max: y1Max,
                            ticks: { color: 'black', font: { size: 9, weight: 'bold' }, stepSize: y1Step },
                            grid:  { display: false },
                            title: { display: true, text: y1Title, color: 'black', font: { size: 10, weight: 'bold' } },
                        },
                    },
                },
            });
        });
    };

    // ── 2) Patientenalter aus Geburtsdatum berechnen ───────────────
    function calculateAge(birthDateString) {
        const birthDate = new Date(birthDateString);
        const today     = new Date();
        if (isNaN(birthDate)) return 0;
        let age   = today.getFullYear() - birthDate.getFullYear();
        const m   = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
        return age >= 0 ? age : 0;
    }
    function updateAge() {
        const el = document.getElementById('patgebdat');
        if (!el) return;
        const ageOut = document.getElementById('_AGE_');
        if (!ageOut) return;
        ageOut.value = calculateAge(el.dataset.date);
    }
    document.addEventListener('DOMContentLoaded', updateAge);

    // ── 3) Zoom + Print-Handling ───────────────────────────────────
    let currentZoom = 1;

    global.zoomIn = function () {
        if (currentZoom < 2) { currentZoom += 0.1; applyZoom(); }
    };
    global.zoomOut = function () {
        if (currentZoom > 0.5) { currentZoom -= 0.1; applyZoom(); }
    };

    function applyZoom() {
        const papers = document.querySelectorAll('.print__paper');
        papers.forEach((paper) => {
            let wrapper = paper.parentElement;
            if (!wrapper.classList.contains('zoom-wrapper')) {
                wrapper = document.createElement('div');
                wrapper.className = 'zoom-wrapper';
                paper.parentNode.insertBefore(wrapper, paper);
                wrapper.appendChild(paper);
            }
            paper.style.transform       = `scale(${currentZoom})`;
            paper.style.transformOrigin = 'top center';

            const naturalHeight = 297; // mm
            const scaledHeight  = naturalHeight * currentZoom;

            wrapper.style.height          = `${scaledHeight}mm`;
            wrapper.style.marginBottom    = '20px';
            wrapper.style.overflow        = 'visible';
            wrapper.style.display         = 'flex';
            wrapper.style.justifyContent  = 'center';
        });
        document.body.style.overflowX = currentZoom > 1 ? 'auto' : 'visible';
    }

    function isInIframe() {
        try { return global.self !== global.top; } catch (e) { return true; }
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (isInIframe()) {
            document.querySelectorAll('.topbar-btn[onclick*="print"]').forEach((btn) => {
                btn.style.display = 'none';
            });
        }
        applyZoom();
    });

    global.addEventListener('beforeprint', function () {
        document.querySelectorAll('.print__paper').forEach((paper) => {
            const wrapper = paper.parentElement;
            if (wrapper && wrapper.classList.contains('zoom-wrapper')) {
                wrapper.parentNode.insertBefore(paper, wrapper);
                wrapper.remove();
            }
            paper.style.transform       = '';
            paper.style.transformOrigin = '';
        });
        document.body.style.overflowX = '';
    });
    global.addEventListener('afterprint', applyZoom);
})(window);
