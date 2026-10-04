/**
 * eNOTF v2: Messwert-Maske mit Keypad und Range-Strip (v1 verlauf/add.php
 * und erstbefund/messwerte/index.php). Genutzt von der Erstbefund-Maske
 * (?t=messwerte) und vom Verlauf (?t=add); das Speichern bleibt im
 * jeweiligen Template, weil beide an unterschiedliche APIs gehen.
 *
 * Markup-Vertrag: Inputs .keypad-input mit id === name (spo2, atemfreq, …),
 * #rangeStrip, Buttons [data-keypad-digit], [data-keypad-clear],
 * [data-keypad-backspace] und optional [data-keypad-set="ng|nm"].
 *
 *   EnotfV2VitalsKeypad.init({
 *       bzUnit: 'mg/dl' | 'mmol/l',
 *       required: ['spo2', …],   // leer = rot markiert (v1 .edivi__vitalparam-required)
 *       locked: false,           // gesperrtes Protokoll: Keypad ohne Wirkung
 *   });
 */
(function () {
    'use strict';

    var SPECIALS = ['ng', 'nm'];

    // Färbung des Feldwerts wie v1 validateField()
    function grade(name, value, bzUnit) {
        switch (name) {
            case 'spo2':
                if (value < 87) return 'danger';
                if (value < 92) return 'warning';
                if (value < 97) return 'semiwarning';
                return 'success';
            case 'atemfreq':
                if (value < 5 || value > 25) return 'danger';
                if (value === 5 || value === 6 || (value > 20 && value < 26)) return 'warning';
                if ((value > 6 && value < 9) || (value > 15 && value < 21)) return 'semiwarning';
                return 'success';
            case 'etco2':
                if (value < 6 || value > 55) return 'danger';
                if (value < 36 || value > 45) return 'semiwarning';
                return 'success';
            case 'rrsys':
                if (value < 80 || value > 199) return 'danger';
                if ((value >= 80 && value < 90) || value > 169) return 'warning';
                if ((value >= 90 && value < 101) || value > 149) return 'semiwarning';
                return 'success';
            case 'rrdias':
                if (value <= 40 || value >= 121) return 'danger';
                if ((value >= 41 && value <= 50) || (value >= 111 && value <= 120)) return 'warning';
                if ((value >= 51 && value <= 60) || (value >= 101 && value <= 110)) return 'semiwarning';
                return 'success';
            case 'herzfreq':
                if (value < 41 || value > 160) return 'danger';
                if (value < 51 || value > 130) return 'warning';
                if (value < 61 || value > 100) return 'semiwarning';
                return 'success';
            case 'bz':
                if (bzUnit === 'mmol/l') {
                    if (value < 2.2 || value > 13.9) return 'danger';
                    if ((value >= 2.2 && value < 2.8) || (value >= 10.0 && value < 13.9)) return 'warning';
                    if ((value >= 2.8 && value < 4.5) || (value >= 8.3 && value < 10.0)) return 'semiwarning';
                    return 'success';
                }
                if (value < 40 || value > 250) return 'danger';
                if ((value >= 40 && value < 51) || (value >= 180 && value < 250)) return 'warning';
                if ((value >= 51 && value < 81) || (value >= 150 && value < 180)) return 'semiwarning';
                return 'success';
            case 'temp':
                if (value <= 34 || value > 40) return 'danger';
                if (value < 36.1 || value > 38) return 'semiwarning';
                return 'success';
        }
        return null;
    }

    function colorRanges(bzUnit) {
        return {
            spo2: [
                { min: 97, max: 100, cls: 'success' },
                { min: 92, max: 97, cls: 'semiwarning' },
                { min: 87, max: 92, cls: 'warning' },
                { min: 70, max: 87, cls: 'danger' }
            ],
            atemfreq: [
                { min: 26, max: 35, cls: 'danger' },
                { min: 21, max: 26, cls: 'warning' },
                { min: 16, max: 21, cls: 'semiwarning' },
                { min: 9, max: 16, cls: 'success' },
                { min: 7, max: 9, cls: 'semiwarning' },
                { min: 5, max: 7, cls: 'warning' },
                { min: 0, max: 5, cls: 'danger' }
            ],
            etco2: [
                { min: 55, max: 60, cls: 'danger' },
                { min: 45, max: 55, cls: 'semiwarning' },
                { min: 36, max: 45, cls: 'success' },
                { min: 6, max: 36, cls: 'semiwarning' },
                { min: 0, max: 6, cls: 'danger' }
            ],
            herzfreq: [
                { min: 160, max: 210, cls: 'danger' },
                { min: 130, max: 160, cls: 'warning' },
                { min: 100, max: 130, cls: 'semiwarning' },
                { min: 61, max: 100, cls: 'success' },
                { min: 51, max: 61, cls: 'semiwarning' },
                { min: 41, max: 51, cls: 'warning' },
                { min: 20, max: 41, cls: 'danger' }
            ],
            rrsys: [
                { min: 199, max: 260, cls: 'danger' },
                { min: 169, max: 199, cls: 'warning' },
                { min: 149, max: 169, cls: 'semiwarning' },
                { min: 101, max: 149, cls: 'success' },
                { min: 90, max: 101, cls: 'semiwarning' },
                { min: 80, max: 90, cls: 'warning' },
                { min: 0, max: 80, cls: 'danger' }
            ],
            rrdias: [
                { min: 121, max: 140, cls: 'danger' },
                { min: 111, max: 121, cls: 'warning' },
                { min: 101, max: 111, cls: 'semiwarning' },
                { min: 61, max: 101, cls: 'success' },
                { min: 51, max: 61, cls: 'semiwarning' },
                { min: 41, max: 51, cls: 'warning' },
                { min: 0, max: 41, cls: 'danger' }
            ],
            bz: bzUnit === 'mmol/l' ? [
                { min: 13.9, max: 20, cls: 'danger' },
                { min: 10.0, max: 13.9, cls: 'warning' },
                { min: 8.3, max: 10.0, cls: 'semiwarning' },
                { min: 4.5, max: 8.3, cls: 'success' },
                { min: 2.8, max: 4.5, cls: 'semiwarning' },
                { min: 2.2, max: 2.8, cls: 'warning' },
                { min: 0, max: 2.2, cls: 'danger' }
            ] : [
                { min: 250, max: 360, cls: 'danger' },
                { min: 180, max: 250, cls: 'warning' },
                { min: 150, max: 180, cls: 'semiwarning' },
                { min: 81, max: 150, cls: 'success' },
                { min: 51, max: 81, cls: 'semiwarning' },
                { min: 40, max: 51, cls: 'warning' },
                { min: 0, max: 40, cls: 'danger' }
            ],
            temp: [
                { min: 40, max: 44, cls: 'danger' },
                { min: 38, max: 40, cls: 'semiwarning' },
                { min: 36.1, max: 38, cls: 'success' },
                { min: 34, max: 36.1, cls: 'semiwarning' },
                { min: 30, max: 34, cls: 'danger' }
            ]
        };
    }

    function rangeConfigs(bzUnit) {
        return {
            spo2: { values: [72, 74, 76, 78, 80, 82, 84, 86, 88, 90, 92, 94, 96, 98], min: 70, max: 100 },
            atemfreq: { values: [5, 10, 15, 20, 25, 30], min: 0, max: 35 },
            etco2: { values: [5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55], min: 0, max: 60 },
            herzfreq: { values: [40, 60, 80, 100, 120, 140, 160, 180, 200], min: 20, max: 210 },
            rrsys: { values: [10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 110, 120, 130, 140, 150, 160, 170, 180, 190, 200, 210, 220, 230, 240, 250], min: 0, max: 260 },
            rrdias: { values: [10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 110, 120, 130], min: 0, max: 140 },
            bz: bzUnit === 'mmol/l'
                ? { values: [1.1, 2.2, 3.3, 4.4, 5.6, 6.7, 7.8, 8.9, 10.0, 11.1, 12.2, 13.3, 14.4, 15.6, 16.7, 17.8, 18.9], min: 0, max: 20 }
                : { values: [20, 40, 60, 80, 100, 120, 140, 160, 180, 200, 220, 240, 260, 280, 300, 320, 340], min: 0, max: 360 },
            temp: { values: [32, 34, 36, 38, 40, 42], min: 30, max: 44 }
        };
    }

    function init(options) {
        var opts = options || {};
        var bzUnit = opts.bzUnit || 'mg/dl';
        var required = opts.required || [];
        var locked = !!opts.locked;
        var ranges = colorRanges(bzUnit);
        var configs = rangeConfigs(bzUnit);
        var stripEl = document.getElementById('rangeStrip');
        var inputs = Array.prototype.slice.call(document.querySelectorAll('.keypad-input'));
        var current = null;

        function validate(field) {
            field.classList.remove('text-warning', 'text-danger', 'text-success', 'text-semiwarning');
            if (required.indexOf(field.id) !== -1) {
                field.classList.toggle('edivi__vitalparam-required', field.value.trim() === '');
            }
            var raw = field.value.trim().toLowerCase();
            if (raw === '' || SPECIALS.indexOf(raw) !== -1) return;
            var value = parseFloat(raw.replace(',', '.'));
            if (isNaN(value)) return;
            var cls = grade(field.name, value, bzUnit);
            if (cls) field.classList.add('text-' + cls);
        }

        function updateIndicator(field) {
            if (!stripEl) return;
            var existing = stripEl.querySelector('.value-indicator');
            if (existing) existing.remove();
            var config = configs[field.id];
            if (!config) return;
            var raw = field.value.trim().toLowerCase();
            if (raw === '' || SPECIALS.indexOf(raw) !== -1) return;
            var num = parseFloat(raw.replace(',', '.'));
            if (isNaN(num)) return;

            var pos = 100 - ((num - config.min) / (config.max - config.min)) * 100;
            var indicator = document.createElement('div');
            indicator.className = 'value-indicator';
            indicator.style.top = Math.max(0, Math.min(100, pos)) + '%';
            indicator.setAttribute('data-value', field.value);
            stripEl.appendChild(indicator);
        }

        function renderStrip(field) {
            if (!stripEl) return;
            var config = field ? configs[field.id] : null;
            if (!config) { stripEl.innerHTML = ''; return; }

            var segs = ranges[field.id] || [];
            var total = config.max - config.min;
            var html = '';
            segs.forEach(function (range, index) {
                html += '<div class="range-segment ' + range.cls + '" style="flex: ' + ((range.max - range.min) / total) * 100 + '; position: relative;">';
                config.values.filter(function (val) {
                    return val >= range.min && (index === segs.length - 1 ? val <= range.max : val < range.max);
                }).sort(function (a, b) { return b - a; }).forEach(function (val) {
                    var posFromTop = (1 - (val - config.min) / total) * 100;
                    var segStart = (1 - (range.max - config.min) / total) * 100;
                    var segEnd = (1 - (range.min - config.min) / total) * 100;
                    html += '<span class="range-value" style="position: absolute; top: '
                        + ((posFromTop - segStart) / (segEnd - segStart)) * 100
                        + '%; left: 50%; transform: translate(-50%, -50%);">' + val + '</span>';
                });
                html += '</div>';
            });
            stripEl.innerHTML = html;
            updateIndicator(field);
        }

        function select(field) {
            inputs.forEach(function (input) { input.classList.remove('active-field'); });
            field.classList.add('active-field');
            current = field;
            renderStrip(field);
        }

        function target() {
            if (!current) {
                var first = document.getElementById('spo2');
                if (first) select(first);
            }
            return current;
        }

        function setValue(field, value) {
            field.value = value;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.focus();
        }

        inputs.forEach(function (input) {
            input.addEventListener('focus', function () { select(input); });
            input.addEventListener('input', function () {
                validate(input);
                if (input === current) updateIndicator(input);
            });
            validate(input);
        });

        function onKey(selector, handler) {
            document.querySelectorAll(selector).forEach(function (btn) {
                // mousedown verhindern: der Fokus bleibt im aktiven Feld
                btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
                btn.addEventListener('click', function () {
                    if (locked) return;
                    var field = target();
                    if (field) handler(field, btn);
                });
            });
        }

        onKey('[data-keypad-digit]', function (field, btn) {
            var digit = btn.dataset.keypadDigit;
            var value = SPECIALS.indexOf(field.value) !== -1 ? '' : field.value;
            // Komma-/Stellen-Regeln wie v1: max 3 Vorkomma-, 2 Nachkommastellen
            if (digit === ',') {
                if (value.indexOf(',') !== -1) return;
                value = value === '' ? '0,' : value + ',';
            } else if (value.indexOf(',') !== -1) {
                var parts = value.split(',');
                if ((parts[1] || '').length >= 2) return;
                value = parts[0] + ',' + (parts[1] || '') + digit;
            } else {
                if (value.length >= 3) return;
                value += digit;
            }
            setValue(field, value);
        });
        onKey('[data-keypad-clear]', function (field) { setValue(field, ''); });
        onKey('[data-keypad-backspace]', function (field) { setValue(field, field.value.slice(0, -1)); });
        onKey('[data-keypad-set]', function (field, btn) { setValue(field, btn.dataset.keypadSet); });

        renderStrip(null);
    }

    window.EnotfV2VitalsKeypad = { init: init, grade: grade };
})();
