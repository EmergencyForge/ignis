<?php
require_once __DIR__ . '/conditions.php';
$_enotfConditions = enotf_get_conditions_for_js();
$_enotfTransportziel = isset($daten['transportziel']) ? (string)(int)$daten['transportziel'] : '';
?>
<script>
(function() {
    // ──── Conditions-Daten von PHP ────
    var CONDITIONS = <?= json_encode($_enotfConditions) ?>;
    var currentTransportziel = <?= json_encode($_enotfTransportziel) ?>;

    /**
     * Berechnet welche HTML-Felder bei der aktuellen Versorgungsart Pflicht sind.
     * Gibt ein Set von HTML-name-Attributen zurück.
     */
    function getRequiredHtmlNames(tz) {
        var names = {};
        var base = CONDITIONS.base;
        var overrides = CONDITIONS.overrides[String(tz)] || [];
        var additions = CONDITIONS.additions[String(tz)] || {};

        // Basis-Felder hinzufügen (außer overridden)
        for (var key in base) {
            if (overrides.indexOf(key) === -1) {
                var htmlNames = base[key].html || [];
                for (var i = 0; i < htmlNames.length; i++) {
                    names[htmlNames[i]] = true;
                }
            }
        }

        // Additions hinzufügen
        for (var addKey in additions) {
            var addHtml = additions[addKey].html || [];
            for (var j = 0; j < addHtml.length; j++) {
                names[addHtml[j]] = true;
            }
        }

        return names;
    }

    /**
     * Wendet Conditions an: Setzt edivi__input-check nur auf Pflichtfelder,
     * entfernt es von optionalen und setzt edivi__input-optional.
     */
    function applyConditions(tz) {
        var requiredNames = getRequiredHtmlNames(tz);

        // Alle Felder mit edivi__input-check ODER edivi__input-optional durchgehen
        var allFields = document.querySelectorAll('.edivi__input-check, .edivi__input-optional');
        allFields.forEach(function(el) {
            var name = el.getAttribute('name');
            if (!name) return;

            if (requiredNames[name]) {
                // Pflichtfeld: edivi__input-check setzen
                el.classList.add('edivi__input-check');
                el.classList.remove('edivi__input-optional');
                el.removeAttribute('data-condition-optional');
            } else {
                // Optionales Feld: check entfernen
                el.classList.remove('edivi__input-check', 'edivi__input-checked');
                el.classList.add('edivi__input-optional');
                el.setAttribute('data-condition-optional', '1');
                el.style.borderLeft = '';
            }
        });

        // Gruppen-Headings: automatisch aus Kindern berechnen
        document.querySelectorAll('h5.edivi__group-check, h5.edivi__group-optional').forEach(function(heading) {
            var box = heading.closest('.edivi__box');
            if (!box) return;

            var requiredChildren = box.querySelectorAll('.edivi__input-check');
            if (requiredChildren.length === 0) {
                // Keine Pflichtfelder mehr in der Gruppe → optional
                heading.classList.remove('edivi__group-check', 'edivi__group-checked', 'edivi__group-partchecked');
                heading.classList.add('edivi__group-optional');
            } else {
                // Mindestens ein Pflichtfeld → Gruppe ist aktiv
                heading.classList.remove('edivi__group-optional');
                heading.classList.add('edivi__group-check');
            }
        });
    }

    /**
     * Prüft ob ein einzelnes Input-Feld ausgefüllt ist und setzt edivi__input-checked.
     */
    function toggleInputChecked(el) {
        // Nur auf Pflichtfelder anwenden
        if (!el.classList.contains('edivi__input-check')) return;

        if (el.tagName === 'SELECT') {
            var opt = el.querySelector('option:checked');
            if (opt && !opt.disabled) {
                el.classList.add('edivi__input-checked');
            } else {
                el.classList.remove('edivi__input-checked');
            }
        } else {
            if (el.value && el.value.trim() !== '') {
                el.classList.add('edivi__input-checked');
            } else {
                el.classList.remove('edivi__input-checked');
            }
        }

        // In Gruppen: individuellen Border ausblenden (Gruppe zeigt Status)
        var box = el.closest('.edivi__box');
        var groupHeading = box ? box.querySelector('h5.edivi__group-check') : null;
        if (groupHeading) {
            el.style.borderLeft = '0';
        } else {
            el.style.borderLeft = '';
        }
    }

    /**
     * Berechnet den Status aller Gruppen-Headings (rot/gelb/grün).
     */
    function checkGroupStatus() {
        document.querySelectorAll('h5.edivi__group-check').forEach(function(heading) {
            var box = heading.closest('.edivi__box');
            if (!box) return;

            var inputs = box.querySelectorAll('.edivi__input-check');
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
            });

            heading.classList.remove('edivi__group-checked', 'edivi__group-partchecked');
            if (filled === total && total > 0) {
                heading.classList.add('edivi__group-checked');
            } else if (filled > 0) {
                heading.classList.add('edivi__group-partchecked');
            }
        });
    }

    // ──── Initialisierung ────

    // 1. Conditions anwenden (optional/pflicht bestimmen)
    applyConditions(currentTransportziel);

    // 2. Alle Pflichtfelder initial prüfen
    document.querySelectorAll('.edivi__input-check').forEach(function(el) {
        toggleInputChecked(el);
        el.addEventListener('input', function() {
            toggleInputChecked(el);
            checkGroupStatus();
        });
        el.addEventListener('change', function() {
            toggleInputChecked(el);
            checkGroupStatus();
        });
    });

    // 3. Gruppen-Status berechnen
    checkGroupStatus();

    // 4. Auf Versorgung-Dropdown-Änderungen reagieren (live auf rettdaten/index.php)
    document.addEventListener('DOMContentLoaded', function() {
        var tzSelect = document.getElementById('transportziel');
        if (tzSelect) {
            tzSelect.addEventListener('change', function() {
                currentTransportziel = this.value;
                applyConditions(this.value);

                // Event-Listener auf neu aktive Felder setzen
                document.querySelectorAll('.edivi__input-check').forEach(function(el) {
                    if (!el._conditionListenerAdded) {
                        el._conditionListenerAdded = true;
                        el.addEventListener('input', function() {
                            toggleInputChecked(el);
                            checkGroupStatus();
                        });
                        el.addEventListener('change', function() {
                            toggleInputChecked(el);
                            checkGroupStatus();
                        });
                    }
                });

                // Alle Felder neu prüfen
                document.querySelectorAll('.edivi__input-check').forEach(toggleInputChecked);
                checkGroupStatus();
            });
        }
    });

    // 5. Re-check nach Custom-Dropdown-Initialisierung
    setTimeout(function() {
        applyConditions(currentTransportziel);
        document.querySelectorAll('.edivi__input-check').forEach(toggleInputChecked);
        checkGroupStatus();
    }, 500);

    // 6. Klickbare Boxen (Navigation)
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.edivi__box-clickable').forEach(function(box) {
            box.addEventListener('click', function() {
                window.location.href = this.getAttribute('data-href');
            });
        });
    });
})();
</script>
