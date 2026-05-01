/**
 * Geteilte DataTables-Default-Konfiguration für ıgnıs.
 *
 * Statt das deutsche `language`-Objekt in jedem Template zu wiederholen,
 * importiert / referenziert man hier:
 *
 *   <script type="module">
 *     import { ignisDataTableLang } from '/assets/js/modules/datatables-config.js';
 *     $('#myTable').DataTable({
 *         ...
 *         language: ignisDataTableLang('Einträge'),
 *     });
 *   </script>
 *
 * Oder via globalem Helper (für Legacy-Inline-Scripts ohne ES-Module):
 *
 *   $('#myTable').DataTable({
 *       ...
 *       language: window.IgnisDataTableLang('Einträge'),
 *   });
 *
 * Der `entityLabel` wird in `info`, `infoFiltered` und `lengthMenu`
 * eingesetzt (z. B. „Gefiltert von _MAX_ <entityLabel>").
 */

export function ignisDataTableLang(entityLabel = 'Einträge') {
    return {
        decimal: '',
        emptyTable: 'Keine Daten vorhanden',
        info: 'Zeige _START_ bis _END_  | Gesamt: _TOTAL_',
        infoEmpty: 'Keine Daten verfügbar',
        infoFiltered: `| Gefiltert von _MAX_ ${entityLabel}`,
        infoPostFix: '',
        thousands: ',',
        lengthMenu: `_MENU_ ${entityLabel} pro Seite anzeigen`,
        loadingRecords: 'Lade...',
        processing: 'Verarbeite...',
        search: `${entityLabel} suchen:`,
        zeroRecords: 'Keine Einträge gefunden',
        paginate: {
            first: 'Erste',
            last: 'Letzte',
            next: 'Nächste',
            previous: 'Vorherige',
        },
        aria: {
            sortAscending: ': aktivieren, um Spalte aufsteigend zu sortieren',
            sortDescending: ': aktivieren, um Spalte absteigend zu sortieren',
        },
    };
}

/**
 * Häufig wiederkehrende DataTables-Defaults (paging, columnDefs, …),
 * lassen sich pro Tabelle mit eigenen Optionen mergen.
 */
export const ignisDataTableDefaults = {
    stateSave: true,
    paging: true,
    lengthMenu: [10, 25, 50, 100],
    pageLength: 25,
    columnDefs: [{ orderable: false, targets: -1 }],
};

if (typeof window !== 'undefined') {
    window.IgnisDataTableLang = ignisDataTableLang;
    window.IgnisDataTableDefaults = ignisDataTableDefaults;
}

// ── Sort-Plugin: deutsches Datum (TT.MM.JJJJ [HH:MM[:SS]]) ───────────
//
// Ohne Plugin sortiert DataTables solche Spalten lexikographisch
// ("01.05.2026" vor "02.11.2025", weil "01" < "02"). Ein Type-Detector
// erkennt das Format, und der zugehoerige `*-pre`-Hook liefert die
// Spalte als Timestamp ans Sortier-Comparator.
//
// Format-Toleranz:
//   - TT.MM.JJJJ
//   - TT.MM.JJJJ HH:MM
//   - TT.MM.JJJJ HH:MM:SS
// Leere Zellen / "-" / "—" werden als 0 sortiert (kommen ans Ende
// bei desc, an den Anfang bei asc — Standard-DT-Verhalten).
if (typeof window !== 'undefined' && window.jQuery && window.jQuery.fn.dataTable) {
    const DT = window.jQuery.fn.dataTable;
    const DE_DATE_RE = /^(\d{2})\.(\d{2})\.(\d{4})(?:[\s,]+(\d{2}):(\d{2})(?::(\d{2}))?)?$/;

    function parseDeDate(value) {
        if (typeof value !== 'string') return null;
        const trimmed = value.trim();
        if (trimmed === '' || trimmed === '-' || trimmed === '—') return 0;
        const m = trimmed.match(DE_DATE_RE);
        if (!m) return null;
        // Date-Konstruktor mit Local-TZ — DataTables vergleicht nur die
        // resultierenden Timestamps, also ist die Zone egal solange sie
        // konsistent ist.
        return new Date(+m[3], +m[2] - 1, +m[1], +(m[4] || 0), +(m[5] || 0), +(m[6] || 0)).getTime();
    }

    DT.ext.type.detect.unshift(function (data) {
        if (data === null || data === undefined) return null;
        return parseDeDate(typeof data === 'string' ? data : '') !== null ? 'de-date' : null;
    });

    DT.ext.type.order['de-date-pre'] = function (data) {
        const ts = parseDeDate(typeof data === 'string' ? data : '');
        return ts === null ? 0 : ts;
    };
}
