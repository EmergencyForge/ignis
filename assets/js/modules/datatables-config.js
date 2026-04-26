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
