<?php
require_once __DIR__ . '/conditions.php';
$_enotfConditions = enotf_get_conditions_for_js();
$_enotfTransportziel = isset($daten['transportziel']) ? (string)(int)$daten['transportziel'] : '';
?>
<script>
    // ──── Conditions-Daten von PHP (global für notify.php) ────
    var CONDITIONS = <?= json_encode($_enotfConditions) ?>;
    var _enotfCurrentTransportziel = <?= json_encode($_enotfTransportziel) ?>;

    /**
     * Berechnet welche HTML-Felder bei der aktuellen Versorgungsart Pflicht sind.
     */
    function _enotfGetRequiredHtmlNames(tz) {
        var names = {};
        var base = CONDITIONS.base;
        var overrides = CONDITIONS.overrides[String(tz)] || [];
        var additions = CONDITIONS.additions[String(tz)] || {};

        for (var key in base) {
            if (overrides.indexOf(key) === -1) {
                var htmlNames = base[key].html || [];
                for (var i = 0; i < htmlNames.length; i++) {
                    names[htmlNames[i]] = true;
                }
            }
        }
        for (var addKey in additions) {
            var addHtml = additions[addKey].html || [];
            for (var j = 0; j < addHtml.length; j++) {
                names[addHtml[j]] = true;
            }
        }
        return names;
    }

    /**
     * Berechnet welche DB-Spalten bei der aktuellen Versorgungsart aktiv/Pflicht sind.
     * Wird von validateLinks in notify.php genutzt.
     */
    function _enotfGetActiveDbCols(tz) {
        var cols = {};
        var base = CONDITIONS.base;
        var overrides = CONDITIONS.overrides[String(tz)] || [];
        var additions = CONDITIONS.additions[String(tz)] || {};

        for (var key in base) {
            if (overrides.indexOf(key) === -1) {
                var dbCols = base[key].db || [];
                for (var i = 0; i < dbCols.length; i++) {
                    cols[dbCols[i]] = true;
                }
            }
        }
        for (var addKey in additions) {
            var addDb = additions[addKey].db || [];
            for (var j = 0; j < addDb.length; j++) {
                cols[addDb[j]] = true;
            }
        }
        return cols;
    }

    /**
     * Icon-Cache: einmal pro Feld erstellt, dann wiederverwendet.
     */
    var _enotfIconCache = {};

    /**
     * Findet oder erstellt das Validierungs-Icon für ein Feld.
     */
    function _enotfGetOrCreateIcon(el) {
        try {
            var name = el.getAttribute('name');
            if (!name) return null;

            // _datum Felder teilen sich das Icon mit dem Hauptfeld
            var baseName = name.replace('_datum', '');

            // Cache-Hit
            if (_enotfIconCache[baseName]) return _enotfIconCache[baseName];

            // Existierendes Icon im DOM suchen (z.B. manuell im PHP gesetzt)
            var icon = document.getElementById('icon-' + baseName);
            if (icon) {
                _enotfIconCache[baseName] = icon;
                return icon;
            }

            // Nur ein Icon pro Basis-Feld erstellen (nicht für _datum)
            if (name !== baseName) return null;

            // Nicht in einer edivi__box? Kein Icon nötig.
            if (!el.closest('.edivi__box')) return null;

            // Label finden — verschiedene DOM-Strukturen unterstützen
            var label = null;
            // 1. edivi__description im gleichen Bootstrap-col (col, col-6, col-sm-4, etc.)
            var col = el.closest('div[class*="col"]');
            if (col) {
                label = col.querySelector('.edivi__description');
                if (!label) label = col.querySelector('label');
            }
            // 2. Fallback: im gleichen .row nach .edivi__description suchen
            if (!label) {
                var row = el.closest('.row');
                if (row) {
                    label = row.querySelector('.edivi__description');
                    if (!label) label = row.querySelector('label');
                }
            }
            // 3. Fallback: im gleichen edivi__box
            if (!label) {
                var box = el.closest('.edivi__box');
                if (box) {
                    label = box.querySelector('.edivi__description');
                }
            }
            if (!label) return null;

            // Duplikat-Check: bereits ein Exclamation-Icon im Label?
            var existing = label.querySelector('.fa-circle-exclamation');
            if (existing) {
                _enotfIconCache[baseName] = existing;
                if (!existing.id) existing.id = 'icon-' + baseName;
                return existing;
            }

            // Icon erstellen
            icon = document.createElement('i');
            icon.id = 'icon-' + baseName;
            icon.className = 'fa-solid fa-circle-exclamation';
            icon.style.cssText = 'color:#d91425; margin-left:4px; display:none;';
            label.appendChild(document.createTextNode(' '));
            label.appendChild(icon);

            _enotfIconCache[baseName] = icon;
            return icon;
        } catch (e) {
            return null;
        }
    }

    /**
     * Wendet Conditions an: Setzt edivi__input-check nur auf Pflichtfelder.
     * Global verfügbar für notify.php.
     */
    function applyConditions(tz) {
        var requiredNames = _enotfGetRequiredHtmlNames(tz);

        // Alle Felder mit edivi__input-check ODER edivi__input-optional durchgehen
        document.querySelectorAll('.edivi__input-check, .edivi__input-optional').forEach(function(el) {
            var name = el.getAttribute('name');
            if (!name) return;

            // Custom-Dropdown-Container finden (falls vorhanden)
            var ddWrapper = el.closest('.enotf-dropdown-wrapper');
            var ddContainer = ddWrapper ? ddWrapper.querySelector('.enotf-dropdown-container') : null;

            var icon = _enotfGetOrCreateIcon(el);

            if (requiredNames[name]) {
                el.classList.add('edivi__input-check');
                el.classList.remove('edivi__input-optional');
                if (icon) {
                    var isEmpty = false;
                    if (el.tagName === 'SELECT') {
                        var opt = el.querySelector('option:checked');
                        isEmpty = !opt || opt.disabled;
                    } else {
                        isEmpty = !el.value || el.value.trim() === '';
                    }
                    icon.style.display = isEmpty ? '' : 'none';
                }
                // Border auf Container auch unterdrücken
                if (ddContainer) ddContainer.style.borderLeft = '0';
            } else {
                el.classList.remove('edivi__input-check', 'edivi__input-checked');
                el.classList.add('edivi__input-optional');
                el.style.borderLeft = '';
                if (ddContainer) ddContainer.style.borderLeft = '';
                if (icon) icon.style.display = 'none';
            }
        });

        // Gruppen-Headings automatisch aus Kindern berechnen
        document.querySelectorAll('h5.edivi__group-check, h5.edivi__group-optional').forEach(function(heading) {
            var box = heading.closest('.edivi__box');
            if (!box) return;
            var remaining = box.querySelectorAll('.edivi__input-check');
            if (remaining.length === 0) {
                heading.classList.remove('edivi__group-check', 'edivi__group-checked', 'edivi__group-partchecked');
                heading.classList.add('edivi__group-optional');
            } else {
                heading.classList.remove('edivi__group-optional');
                heading.classList.add('edivi__group-check');
            }
        });
    }

    /**
     * Prüft ob ein einzelnes Input-Feld ausgefüllt ist.
     */
    function _enotfToggleInputChecked(el) {
        if (!el.classList.contains('edivi__input-check')) return;

        var isFilled = false;
        if (el.tagName === 'SELECT') {
            var opt = el.querySelector('option:checked');
            if (opt && !opt.disabled) {
                el.classList.add('edivi__input-checked');
                isFilled = true;
            } else {
                el.classList.remove('edivi__input-checked');
            }
        } else {
            if (el.value && el.value.trim() !== '') {
                el.classList.add('edivi__input-checked');
                isFilled = true;
            } else {
                el.classList.remove('edivi__input-checked');
            }
        }

        // Icon aktualisieren
        var icon = _enotfGetOrCreateIcon(el);
        if (icon) icon.style.display = isFilled ? 'none' : '';

        // Border in Boxen immer unterdrücken (Icons übernehmen)
        el.style.borderLeft = '0';
        // Auch auf Custom-Dropdown-Container anwenden
        var wrapper = el.closest('.enotf-dropdown-wrapper');
        if (wrapper) {
            var container = wrapper.querySelector('.enotf-dropdown-container');
            if (container) container.style.borderLeft = '0';
        }
    }

    /**
     * Berechnet den Status aller Gruppen-Headings (rot/gelb/grün).
     */
    function _enotfCheckGroupStatus() {
        document.querySelectorAll('h5.edivi__group-check').forEach(function(heading) {
            var box = heading.closest('.edivi__box');
            if (!box) return;

            var inputs = box.querySelectorAll('input.edivi__input-check, select.edivi__input-check, textarea.edivi__input-check');
            var filled = 0;
            var total = inputs.length;

            inputs.forEach(function(input) {
                var isFilled = false;
                if (input.tagName === 'SELECT') {
                    var opt = input.querySelector('option:checked');
                    if (opt && !opt.disabled) isFilled = true;
                } else if (input.value && input.value.trim() !== '') {
                    isFilled = true;
                }
                if (isFilled) filled++;
                input.style.borderLeft = '0';
                // Custom-Dropdown-Container Border auch unterdrücken
                var ddW = input.closest('.enotf-dropdown-wrapper');
                if (ddW) {
                    var ddC = ddW.querySelector('.enotf-dropdown-container');
                    if (ddC) ddC.style.borderLeft = '0';
                }
            });

            heading.classList.remove('edivi__group-checked', 'edivi__group-partchecked');
            if (filled === total && total > 0) {
                heading.classList.add('edivi__group-checked');
            } else if (filled > 0) {
                heading.classList.add('edivi__group-partchecked');
            }
        });
    }

    /**
     * Vollständiges Re-Apply: Conditions + Input-Checks + Groups + Nav.
     * Aufgerufen bei jeder Versorgung-Änderung (live).
     */
    function enotfReapplyAll(tz) {
        _enotfCurrentTransportziel = String(tz || '');

        // 1. Field-Check Conditions
        applyConditions(tz);

        // 2. Event-Listener auf neu aktive Felder
        document.querySelectorAll('.edivi__input-check').forEach(function(el) {
            if (!el._conditionListenerAdded) {
                el._conditionListenerAdded = true;
                el.addEventListener('input', function() {
                    _enotfToggleInputChecked(el);
                    _enotfCheckGroupStatus();
                });
                el.addEventListener('change', function() {
                    _enotfToggleInputChecked(el);
                    _enotfCheckGroupStatus();
                });
            }
        });

        // 3. Alle Felder neu prüfen
        document.querySelectorAll('.edivi__input-check').forEach(_enotfToggleInputChecked);
        _enotfCheckGroupStatus();

        // 4. Nav data-requires aktualisieren (wenn updateNavRequires existiert)
        if (typeof updateNavRequires === 'function') {
            updateNavRequires(tz);
        }

        // 5. Nav-Fill-States aktualisieren (wenn vorhanden)
        if (typeof updateNavFillStates === 'function' && window.__dynamicDaten) {
            updateNavFillStates(window.__dynamicDaten);
        }

        // 6. Validation-Links aktualisieren (wenn vorhanden)
        if (typeof validateLinks === 'function') {
            validateLinks();
        }
    }

    // ──── Initialisierung ────

    // 1. Conditions anwenden (erstellt auch Icons)
    applyConditions(_enotfCurrentTransportziel);

    // 2. Alle Pflichtfelder initial prüfen + Listener
    document.querySelectorAll('.edivi__input-check').forEach(function(el) {
        el._conditionListenerAdded = true;
        _enotfToggleInputChecked(el);
        el.addEventListener('input', function() {
            _enotfToggleInputChecked(el);
            _enotfCheckGroupStatus();
        });
        el.addEventListener('change', function() {
            _enotfToggleInputChecked(el);
            _enotfCheckGroupStatus();
        });
    });

    // 3. Gruppen-Status
    _enotfCheckGroupStatus();

    // 4. Versorgung-Dropdown: sofort live reagieren
    document.addEventListener('DOMContentLoaded', function() {
        var tzSelect = document.getElementById('transportziel');
        if (tzSelect) {
            tzSelect.addEventListener('change', function() {
                enotfReapplyAll(this.value);
            });
        }
    });

    // 5. Re-check nach Custom-Dropdown-Initialisierung
    setTimeout(function() {
        applyConditions(_enotfCurrentTransportziel);
        document.querySelectorAll('.edivi__input-check').forEach(_enotfToggleInputChecked);
        _enotfCheckGroupStatus();
    }, 500);

    // 6. Klickbare Boxen (Navigation)
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.edivi__box-clickable').forEach(function(box) {
            box.addEventListener('click', function() {
                window.location.href = this.getAttribute('data-href');
            });
        });
    });
</script>
