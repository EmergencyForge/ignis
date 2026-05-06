/**
 * enotf-diagnose.js — Multi-Select-Speicher-Logik für die eNOTF-
 * Weitere-Diagnosen-Kategorieseiten.
 *
 * 18 Templates unter `templates/enotf/protokoll/diagnose/2_*.php` rendern
 * jeweils nur den Checkbox-Block einer Kategorie (z.B. ZNS, Herz, Atemwege).
 * Die zugrunde liegende Save-Logik ist überall identisch:
 *   - Liste aller in der DB gespeicherten Diagnose-IDs einlesen
 *   - Nur die IDs der aktuellen Kategorie aus der Liste werfen
 *   - Mit der aktuellen Checkbox-Auswahl mergen
 *   - Als sortierten JSON-Array auf `diagnose_weitere` zurückspeichern
 *
 * Der gesamte Block landet einmalig hier. Templates rufen:
 *
 *   initEnotfDiagnosePage({
 *       basePath:       '<?= BASE_PATH ?>',
 *       enr:            '<?= $enr ?>',
 *       initialValues:  <?= json_encode($diagnose_weitere) ?>,
 *       readonly:       <?= $ist_freigegeben ? 'true' : 'false' ?>,
 *   });
 */
(function (global) {
    'use strict';

    // Kategorie-Bereiche (Diagnose-IDs pro Kategorie). Wird einmalig zur
    // Laufzeit benutzt, um die ID-Range der aktuell sichtbaren
    // Checkboxen zu bestimmen — der Save-Pfad braucht das, weil andere
    // Kategorien beim Submit unangetastet bleiben sollen.
    const CATEGORY_RANGES = {
        zns:          [1, 2, 3, 4, 5, 6, 9],
        herz:         [11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29],
        atemwege:     [31, 32, 33, 34, 35, 36, 37, 38, 39, 49],
        abdomen:      [51, 52, 53, 54, 55, 56, 59],
        psychiatrie:  [61, 62, 63, 64, 65, 66, 67, 69],
        stoffwechsel: [71, 72, 73, 74, 75, 79],
        sonstige:     [81, 82, 83, 84, 85, 86, 87, 88, 89, 91, 92, 93, 94, 99],
        trauma:       [
            101, 102, 103, 104, 111, 112, 113, 114, 121, 122, 123, 124,
            131, 132, 133, 134, 141, 142, 143, 144, 151, 152, 153,
            161, 162, 163, 171, 172, 173, 181, 182, 183, 191, 192, 193,
            201, 202, 203, 204, 205, 206, 209,
        ],
    };

    global.initEnotfDiagnosePage = function (config) {
        const $ = global.jQuery || global.$;
        if (!$) return;

        const apiUrl = config.basePath + 'api/enotf/save-fields';
        const enr    = config.enr;
        const isReadonly = config.readonly === true;

        // Im Readonly-Modus (Protokoll bereits freigegeben) werden
        // ohnehin alle Inputs vom Pin-Activity-Wrap-Script disabled.
        // Wir registrieren dann gar keine Save-Handler.
        if (isReadonly) return;

        $(document).ready(function () {
            $('input[name="diagnose_weitere[]"]').off('change');

            // Aktuelle Kategorie anhand der ersten sichtbaren Checkbox bestimmen
            let currentCategoryRange = [];
            const firstCheckbox = $('input[name="diagnose_weitere[]"]').first();
            if (firstCheckbox.length > 0) {
                const firstValue = parseInt(firstCheckbox.val(), 10);
                for (const [, range] of Object.entries(CATEGORY_RANGES)) {
                    if (range.includes(firstValue)) {
                        currentCategoryRange = range;
                        break;
                    }
                }
            }

            // Initial-Werte aus dem Server-Render übernehmen
            let allExistingValues = Array.isArray(config.initialValues)
                ? config.initialValues.slice()
                : [];

            // Debounce, damit ein Schwall an Klicks nicht n Requests auslöst.
            let saveTimer = null;
            $('input[name="diagnose_weitere[]"]').on('change', function () {
                if (saveTimer) clearTimeout(saveTimer);
                saveTimer = setTimeout(performSave, 300);
            });

            function performSave() {
                if (currentCategoryRange.length === 0) return;

                const currentPageValues = [];
                $('input[name="diagnose_weitere[]"]:checked').each(function () {
                    currentPageValues.push(parseInt($(this).val(), 10));
                });

                // Andere Kategorien beibehalten, eigene Range komplett ersetzen
                const otherCategoryValues = allExistingValues.filter((val) => !currentCategoryRange.includes(val));
                const finalValues         = [...otherCategoryValues, ...currentPageValues].sort((a, b) => a - b);
                const jsonValue           = JSON.stringify(finalValues);

                $.ajax({
                    url:      apiUrl,
                    method:   'POST',
                    data:     { enr: enr, field: 'diagnose_weitere', value: jsonValue },
                    dataType: 'text',
                    success:  function (response) {
                        const trimmed = response.trim().toLowerCase();
                        if (trimmed.startsWith('<!doctype') || trimmed.startsWith('<html')) {
                            console.error('Fehler: Server hat HTML zurückgegeben');
                            if (typeof global.showToast === 'function') {
                                global.showToast('Fehler beim Speichern der Diagnosen', 'error');
                            }
                            return;
                        }

                        allExistingValues = finalValues;

                        if (typeof global.showToast === 'function') {
                            const count      = currentPageValues.length;
                            const totalCount = finalValues.length;
                            const message    = count === 0
                                ? `Diagnosen dieser Kategorie zurückgesetzt (${totalCount} gesamt)`
                                : `${count} ${count === 1 ? 'Diagnose' : 'Diagnosen'} dieser Kategorie (${totalCount} gesamt)`;
                            global.showToast(message, 'success');
                        }

                        if (typeof global.__dynamicDaten !== 'undefined') {
                            global.__dynamicDaten['diagnose_weitere'] = jsonValue;
                        }
                    },
                    error: function (xhr, _status, errMsg) {
                        console.error('AJAX Fehler:', errMsg, 'Status:', xhr.status);
                        console.error('Response:', xhr.responseText.substring(0, 500));
                        if (typeof global.showToast === 'function') {
                            global.showToast('Fehler beim Speichern der Diagnosen', 'error');
                        }
                    },
                });
            }
        });
    };
})(window);
