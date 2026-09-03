/**
 * eNOTF v2 — eDIVI-Bridge: Vanilla-Ersatz für v1s notify.php (jQuery).
 *
 * Erwartet vom Layout (_layout-protokoll.php):
 *   window.__dynamicDaten   komplette Protokollzeile (Feld → Wert)
 *   window.__ev2ZeroIsValid Felder, bei denen "0" als gefüllt zählt
 *   window.__ev2Locked      Protokoll gesperrt (freigegeben/gelöscht)
 *
 * Übernimmt aus v1 (identisches Verhalten, identische Klassen):
 *   - Nav-Füllstände:      #edivi__nidanav a[data-requires] →
 *                          edivi__nidanav-filled/-partfilled/-unfilled
 *   - Link-Validierung:    [class*=edivi__interactbutton] a[data-requires] →
 *                          edivi__validation-green/-yellow/-red (gefiltert
 *                          auf die aktiven Conditions-Spalten, wenn
 *                          field_checks.php geladen ist)
 *   - Quickfill-Kacheln:   input[data-quickfill] („ohne path. Befund") —
 *                          setzt die Felder und speichert sie als EINEN
 *                          Batch über den v2-Autosave (statt n Requests)
 *   - psych-Exklusivlogik: 1/98/99 schließen alle anderen aus; Speicherung
 *                          als JSON-Array über EnotfV2Autosave.queue('psych', …)
 *   - Multi-JSON generisch: Checkboxen mit data-ev2-multijson="feld"
 *                          (z. B. diagnose_weitere, ebesonderheiten) werden
 *                          nach dem psych-Muster als JSON-Array von int
 *                          gespeichert; optionale Exklusiv-Codes über
 *                          data-ev2-exclusive="1[,98,…]" (Code schließt
 *                          alle anderen aus und umgekehrt). Nutzt u. a.
 *                          rettungstechnik (massnahmen/weitere) — dort
 *                          bewusst OHNE Exklusiv-Codes: Code 1 ist im
 *                          v1-Formular „Spineboard", kein „keine"-Wert
 *                          (siehe BefundCatalog::RETTUNGSTECHNIK)
 *
 * __dynamicDaten wird bei jeder Feldänderung im DOM nachgezogen — die
 * Färbungen reagieren damit sofort, ohne auf den Server zu warten.
 */
(function () {
    'use strict';

    var EXKLUSIV = [1, 98, 99];

    function zeroIsValid(field) {
        return (window.__ev2ZeroIsValid || []).indexOf(field) !== -1;
    }

    function daten() {
        if (!window.__dynamicDaten) window.__dynamicDaten = {};
        return window.__dynamicDaten;
    }

    function isFilled(field) {
        var val = daten()[field];
        if (val === null || typeof val === 'undefined' || val === '') return false;
        if ((val === 0 || val === '0') && !zeroIsValid(field)) return false;
        return true;
    }

    // requires-Ausdruck ("a,b|c"): Gruppen mit Komma, Alternativen mit Pipe
    function countGroups(requires) {
        var groups = requires.split(',');
        var filled = 0;
        groups.forEach(function (group) {
            var ok = group.split('|').some(function (f) { return isFilled(f.trim()); });
            if (ok) filled++;
        });
        return { filled: filled, total: groups.length };
    }

    function updateNavFillStates() {
        document.querySelectorAll('#edivi__nidanav a[data-requires]').forEach(function (link) {
            var requires = link.dataset.requires;
            if (!requires) return;
            var c = countGroups(requires);
            link.classList.toggle('edivi__nidanav-filled', c.filled === c.total);
            link.classList.toggle('edivi__nidanav-partfilled', c.filled > 0 && c.filled < c.total);
            link.classList.toggle('edivi__nidanav-unfilled', c.filled === 0);
            link.classList.remove('edivi__nidanav-nocheck');
        });
    }

    function validateLinks() {
        // Aktive DB-Spalten aus den Conditions (field_checks.php), damit
        // durch die Versorgungsart deaktivierte Pflichten nicht rot färben
        var activeDbCols = null;
        if (typeof window._enotfGetActiveDbCols === 'function' && typeof window._enotfCurrentTransportziel !== 'undefined') {
            activeDbCols = window._enotfGetActiveDbCols(window._enotfCurrentTransportziel);
        }

        document.querySelectorAll('[class*="edivi__interactbutton"] a[data-requires]').forEach(function (link) {
            var requires = link.dataset.requires;
            if (!requires || link.classList.contains('edivi__validation-ignore')) return;

            var groups = requires.split(',');
            if (activeDbCols) {
                groups = groups.filter(function (group) {
                    return group.split('|').some(function (f) { return activeDbCols[f.trim()]; });
                });
            }

            link.classList.remove('edivi__validation-green', 'edivi__validation-yellow', 'edivi__validation-red');
            if (groups.length === 0) return;

            var c = countGroups(groups.join(','));
            if (c.filled === c.total) link.classList.add('edivi__validation-green');
            else if (c.filled > 0) link.classList.add('edivi__validation-yellow');
            else link.classList.add('edivi__validation-red');
        });
    }

    // ── Quickfill („ohne path. Befund") ────────────────────────────────
    function quickfillMatches(box) {
        try {
            var data = JSON.parse(box.dataset.quickfill);
            return Object.keys(data).every(function (field) {
                return String(daten()[field]) === String(data[field]);
            });
        } catch (e) { return false; }
    }

    function updateQuickfillBoxes() {
        document.querySelectorAll('input[type="checkbox"][data-quickfill]').forEach(function (box) {
            var should = quickfillMatches(box);
            if (box.checked !== should) box.checked = should;
        });
    }

    function applyQuickfill(box) {
        var data;
        try { data = JSON.parse(box.dataset.quickfill); } catch (e) { return; }
        var autosave = window.EnotfV2Autosave;
        Object.keys(data).forEach(function (field) {
            var value = data[field];
            if (String(daten()[field]) === String(value)) return;

            // DOM nachziehen (alle Schritte stehen im DOM): Radio anhaken
            // bzw. Textwert setzen — OHNE change-Event, gespeichert wird
            // gesammelt über den Autosave-Batch darunter.
            var radio = document.querySelector('input[type="radio"][name="' + CSS.escape(field) + '"][value="' + CSS.escape(String(value)) + '"]');
            if (radio) {
                radio.checked = true;
            } else {
                var input = document.querySelector('input[name="' + CSS.escape(field) + '"]:not([type="radio"]):not([type="checkbox"])');
                if (input) input.value = String(value);
            }

            daten()[field] = value;
            if (autosave) autosave.queue(field, String(value));
        });
        refresh();
    }

    // ── psych: Mehrfachauswahl mit Exklusivwerten, JSON-Array ─────────
    function bindPsych() {
        var boxes = Array.prototype.slice.call(document.querySelectorAll('input[type="checkbox"][name="psych[]"]'));
        if (!boxes.length) return;
        boxes.forEach(function (box) {
            box.addEventListener('change', function () {
                if (window.__ev2Locked) return;
                var code = parseInt(box.value, 10);
                if (box.checked) {
                    if (EXKLUSIV.indexOf(code) !== -1) {
                        boxes.forEach(function (b) { if (b !== box) b.checked = false; });
                    } else {
                        boxes.forEach(function (b) {
                            if (EXKLUSIV.indexOf(parseInt(b.value, 10)) !== -1) b.checked = false;
                        });
                    }
                }
                var selected = boxes
                    .filter(function (b) { return b.checked; })
                    .map(function (b) { return parseInt(b.value, 10); });
                var json = selected.length ? JSON.stringify(selected) : null;
                daten().psych = json;
                if (window.EnotfV2Autosave) window.EnotfV2Autosave.queue('psych', json);
                refresh();
            });
        });
    }

    // ── Multi-JSON generisch: Checkbox-Gruppe → JSON-Array von int ─────
    // Gleiche Speicherlogik wie psych, aber deklarativ über Attribute:
    //   <input type="checkbox" name="feld[]" value="2" data-autosave-ignore
    //          data-ev2-multijson="feld" [data-ev2-exclusive="1"]>
    // Exklusiv-Codes verhalten sich wie 1/98/99 bei psych: ihre Auswahl
    // leert alle anderen, jede andere Auswahl leert die Exklusiv-Codes.
    function bindMultiJson() {
        var groups = {};
        document.querySelectorAll('input[type="checkbox"][data-ev2-multijson]').forEach(function (box) {
            var field = box.dataset.ev2Multijson;
            if (!field) return;
            (groups[field] = groups[field] || []).push(box);
        });

        Object.keys(groups).forEach(function (field) {
            var boxes = groups[field];
            var exklusiv = [];
            boxes.forEach(function (box) {
                if (box.dataset.ev2Exclusive) {
                    exklusiv = box.dataset.ev2Exclusive.split(',')
                        .map(function (n) { return parseInt(n, 10); })
                        .filter(function (n) { return !isNaN(n); });
                }
            });

            boxes.forEach(function (box) {
                box.addEventListener('change', function () {
                    if (window.__ev2Locked) return;
                    var code = parseInt(box.value, 10);
                    if (box.checked && exklusiv.length) {
                        if (exklusiv.indexOf(code) !== -1) {
                            boxes.forEach(function (b) { if (b !== box) b.checked = false; });
                        } else {
                            boxes.forEach(function (b) {
                                if (exklusiv.indexOf(parseInt(b.value, 10)) !== -1) b.checked = false;
                            });
                        }
                    }
                    var selected = boxes
                        .filter(function (b) { return b.checked; })
                        .map(function (b) { return parseInt(b.value, 10); });
                    var json = selected.length ? JSON.stringify(selected) : null;
                    daten()[field] = json;
                    if (window.EnotfV2Autosave) window.EnotfV2Autosave.queue(field, json);
                    refresh();
                });
            });
        });
    }

    // ── symptombeginn: „geschätzt" ↔ „nicht feststellbar" exklusiv ────
    // (v1 anamnese/2_1.php). Die Checkboxen selbst speichert der normale
    // Checkbox-Autosave (1/0); nur das programmatische Abwählen des
    // Gegenstücks feuert kein change-Event — dessen 0 wird deshalb hier
    // explizit mitgequeued (gleiches Muster wie bindPsych).
    function bindSymptombeginn() {
        var geschaetzt = document.querySelector('input[type="checkbox"][name="symptombeginn_geschaetzt"]');
        var nf = document.querySelector('input[type="checkbox"][name="symptombeginn_nf"]');
        if (!geschaetzt || !nf) return;

        function exklusiv(box, other) {
            box.addEventListener('change', function () {
                if (window.__ev2Locked || !box.checked || !other.checked) return;
                other.checked = false;
                daten()[other.name] = 0;
                if (window.EnotfV2Autosave) window.EnotfV2Autosave.queue(other.name, '0');
                refresh();
            });
        }

        exklusiv(geschaetzt, nf);
        exklusiv(nf, geschaetzt);
    }

    // ── __dynamicDaten aus DOM-Änderungen nachziehen ───────────────────
    function syncFromEvent(el) {
        if (!(el instanceof HTMLElement) || !el.name) return;
        if (el.name === 'psych[]') return; // eigener Handler
        if (el.dataset && el.dataset.ev2Multijson) return; // eigener Handler (bindMultiJson)
        if (el instanceof HTMLInputElement && el.type === 'radio') {
            if (el.checked) daten()[el.name] = el.value;
            return;
        }
        if (el instanceof HTMLInputElement && el.type === 'checkbox') {
            daten()[el.name] = el.checked ? 1 : 0;
            return;
        }
        daten()[el.name] = el.value;
    }

    function refresh() {
        updateNavFillStates();
        validateLinks();
        updateQuickfillBoxes();
    }

    function init() {
        document.addEventListener('change', function (event) {
            var el = event.target;
            if (el instanceof HTMLInputElement && el.matches('[data-quickfill]')) {
                if (window.__ev2Locked) { el.checked = quickfillMatches(el); return; }
                if (el.checked) applyQuickfill(el);
                else el.checked = quickfillMatches(el); // Abwählen hat wie in v1 keine Wirkung
                return;
            }
            syncFromEvent(el);
            refresh();
        });
        document.addEventListener('input', function (event) {
            var el = event.target;
            if (el instanceof HTMLElement && el.matches('input[name], textarea[name]')) {
                syncFromEvent(el);
                refresh();
            }
        });

        bindPsych();
        bindMultiJson();
        bindSymptombeginn();
        refresh();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
