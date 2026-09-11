/**
 * eNOTF v2 — Schritt-Umschalter für die ?t-Unterseiten (v1-Look).
 *
 * v1 rendert jede Frage als eigene Unterseite (Full-Page-Reload pro
 * Klick). v2 rendert ALLE Schritte eines Themas in einer Seite und
 * schaltet client-seitig um — gleiche Optik (edivi__interactbutton-
 * Spalten), aber ohne Server-Roundtrip. Autosave/names unverändert.
 *
 * Markup-Vertrag (Section-Template, z. B. erstbefund.php):
 *   <div data-ev2-steps>                      Container (die v1-Row)
 *     <a data-wiz-goto="0" href="?t=…&q=1">   Schritt-Links (Subnav-Spalte);
 *        [data-wiz-covers="2,3"]              optional: bei diesen Schritten
 *                                             als aktiv markieren (v1s
 *                                             verschachtelte Nav, z. B.
 *                                             „Pupillen" → Weite/Reaktion)
 *     <div class="ev2-stepwrap" data-wiz-step data-wiz-fields="a,b">
 *       … edivi__interactbutton-Spalten des Schritts …
 *     </div>
 *   </div>
 *
 * Verhalten (wie v1s Themen-Index-Seiten):
 *   - Einstieg OHNE ?q: KEIN Schritt aktiv — nur die Subnav-Spalte(n)
 *     sind sichtbar (v1: erstbefund/atemwege/index.php zeigt auch nur
 *     die Navigation). Erst der Klick auf einen Subnav-Eintrag öffnet
 *     dessen Frage.
 *   - ?q=N (1-basiert, explizite Links z. B. von den Übersichts-Kacheln)
 *     wählt den Schritt direkt; History-Replace hält q nach Klicks aktuell.
 *   - Container OHNE data-wiz-goto-Links (Themen, die in v1 direkt in die
 *     Antwort-Spalten springen, z. B. EKG/psych): Schritt 1 bleibt offen.
 *   - Gesperrtes Protokoll: Umschalten erlaubt, Felder sind serverseitig
 *     disabled.
 */
(function () {
    'use strict';

    function initSteps(container) {
        var steps = Array.prototype.slice.call(container.querySelectorAll('[data-wiz-step]'));
        if (!steps.length) return;
        var links = Array.prototype.slice.call(container.querySelectorAll('[data-wiz-goto]'));
        var current = -1;

        // -1 = kein Schritt aktiv (nur Subnav, wie v1s Themen-Index)
        function show(i, updateUrl) {
            current = i < 0 ? -1 : Math.max(0, Math.min(steps.length - 1, i));
            steps.forEach(function (step, n) { step.classList.toggle('is-hidden', n !== current); });
            links.forEach(function (link) {
                var covers = (link.dataset.wizCovers || link.dataset.wizGoto)
                    .split(',').map(function (n) { return parseInt(n, 10); });
                link.classList.toggle('active', current !== -1 && covers.indexOf(current) !== -1);
            });
            if (!updateUrl) return;
            try {
                var url = new URL(window.location.href);
                if (current === -1) url.searchParams.delete('q');
                else url.searchParams.set('q', String(current + 1));
                history.replaceState(null, '', url.toString());
            } catch (e) { /* ältere Engines */ }
        }

        links.forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                show(parseInt(link.dataset.wizGoto, 10) || 0, true);
            });
        });

        // Einstieg: nur ein explizites ?q öffnet einen Schritt; Themen ohne
        // Subnav-Links zeigen ihren einzigen Schritt direkt (v1: ekg/psych).
        var start = -1;
        try {
            var q = new URL(window.location.href).searchParams.get('q');
            if (q !== null && /^\d+$/.test(q)) start = parseInt(q, 10) - 1;
        } catch (e) {}
        if (start < 0 && !links.length) start = 0;
        show(start, false);
    }

    function init() {
        document.querySelectorAll('[data-ev2-steps]').forEach(initSteps);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
