/**
 * enotf-zugang.js — gemeinsamer Click-Handler für die elf Zugangs-
 * Auswahl-Templates (PVK an Handrücken, Unterarm, …; i.o. an Tibia
 * proximal/distal/Humeruskopf).
 *
 * Jedes der elf Templates rendert ein Body-Bereichs-Formular mit den
 * Checkboxen für die Zugang-Größen pro Seite. Die Speicher-Logik
 * dahinter ist überall identisch: das Feld `c_zugang` in der eNOTF-
 * Datenbank ist ein JSON-Array von Zugang-Objekten — beim Toggle wird
 * der Eintrag für `(art, ort)` neu gemerged und über die generische
 * `api/enotf/save-fields`-Schnittstelle persistiert.
 *
 * Aufruf vom Template:
 *
 *   initEnotfZugangPage({
 *       basePath: '<?= BASE_PATH ?>',
 *       enr:      '<?= $enr ?>',
 *       art:      'pvk',           // 'pvk' | 'zvk' | 'io'
 *       ort:      'Handrücken',
 *   });
 */
(function (global) {
    'use strict';

    const ART_NAMES = { pvk: 'PVK', zvk: 'ZVK', io: 'i.o.' };

    global.initEnotfZugangPage = function (config) {
        const $ = global.jQuery || global.$;
        if (!$) return;

        const apiUrl = config.basePath + 'api/enotf/save-fields';
        const enr    = config.enr;
        const art    = config.art;
        const ort    = config.ort;

        function showToastIfAvail(message, level) {
            if (typeof global.showToast === 'function') {
                global.showToast(message, level);
            }
        }

        function refreshNav() {
            if (typeof global.updateNavFillStates === 'function') {
                global.updateNavFillStates(global.__dynamicDaten);
            }
            if (typeof global.validateLinks === 'function') {
                global.validateLinks();
            }
            if (typeof global.checkGroupStatus === 'function') {
                global.checkGroupStatus();
            }
        }

        $(document).ready(function () {
            // Checkboxen pro Body-Location: nur eine Größe gleichzeitig auswählbar.
            $('.zugang-checkbox').off('change blur').on('change', function (e) {
                e.stopPropagation();
                const $clicked = $(this);
                const location = $clicked.data('location');

                $(`.zugang-checkbox[data-location="${location}"]`).not($clicked).each(function () {
                    if ($(this).is(':checked')) {
                        $(this).prop('checked', false);
                    }
                });

                updateZugaenge();
            });

            // Master-Checkbox "Keine Zugänge" — leert alle anderen.
            $('#c_zugang-0').off('change').on('change', function () {
                if ($(this).is(':checked')) {
                    $('.zugang-checkbox').prop('checked', false);
                    $.ajax({
                        url:  apiUrl,
                        type: 'POST',
                        data: { enr: enr, field: 'c_zugang', value: '0' },
                        success: function () {
                            if (typeof global.__dynamicDaten !== 'undefined') {
                                global.__dynamicDaten['c_zugang'] = '0';
                                refreshNav();
                            }
                            showToastIfAvail('Alle Zugänge entfernt', 'success');
                        },
                        error: function (xhr) {
                            console.error('Fehler beim Entfernen:', xhr.responseText);
                            showToastIfAvail('Fehler beim Entfernen der Zugänge', 'error');
                        },
                    });
                } else {
                    $.ajax({
                        url:  apiUrl,
                        type: 'POST',
                        data: { enr: enr, field: 'c_zugang', value: null },
                        success: function () {
                            if (typeof global.__dynamicDaten !== 'undefined') {
                                global.__dynamicDaten['c_zugang'] = null;
                                refreshNav();
                            }
                            showToastIfAvail('Zugang-Auswahl zurückgesetzt', 'success');
                        },
                        error: function (xhr) {
                            console.error('Fehler beim Zurücksetzen:', xhr.responseText);
                            showToastIfAvail('Fehler beim Zurücksetzen', 'error');
                        },
                    });
                }
            });

            function updateZugaenge() {
                // Aktuelle Auswahl auf dieser Body-Location einsammeln
                const currentPageZugaenge = [];
                $('.zugang-checkbox:checked').each(function () {
                    currentPageZugaenge.push($(this).data('zugang'));
                });

                // Bestehende Zugang-Liste aus dem dynamicDaten-Cache parsen
                let existingZugaenge = [];
                if (typeof global.__dynamicDaten !== 'undefined' && global.__dynamicDaten['c_zugang']) {
                    const currentData = global.__dynamicDaten['c_zugang'];
                    if (currentData !== '0' && currentData !== 0) {
                        try {
                            const parsed = JSON.parse(currentData);
                            if (Array.isArray(parsed)) {
                                existingZugaenge = parsed;
                            } else if (parsed && typeof parsed === 'object') {
                                existingZugaenge = [parsed];
                            }
                        } catch (err) {
                            console.log('Could not parse existing zugang data:', err);
                            existingZugaenge = [];
                        }
                    }
                }

                // Andere (art, ort)-Kombinationen unverändert lassen, eigene
                // Body-Location komplett durch die aktuelle Auswahl ersetzen.
                let mergedZugaenge = existingZugaenge.filter((z) => !(z.art === art && z.ort === ort));
                mergedZugaenge = mergedZugaenge.concat(currentPageZugaenge);

                if (mergedZugaenge.length > 0) {
                    $('#c_zugang-0').prop('checked', false);
                }

                const dbValue = mergedZugaenge.length === 0 ? '0' : JSON.stringify(mergedZugaenge);

                $.ajax({
                    url:  apiUrl,
                    type: 'POST',
                    data: { enr: enr, field: 'c_zugang', value: dbValue },
                    success: function () {
                        if (typeof global.__dynamicDaten !== 'undefined') {
                            global.__dynamicDaten['c_zugang'] = dbValue;
                            refreshNav();
                        }

                        if (mergedZugaenge.length === 0) {
                            showToastIfAvail('Zugang entfernt', 'success');
                        } else if (currentPageZugaenge.length > 0) {
                            const last     = currentPageZugaenge[currentPageZugaenge.length - 1];
                            const artLabel = ART_NAMES[last.art] || last.art;
                            showToastIfAvail(`${artLabel} ${last.groesse} an ${last.ort} ${last.seite} gespeichert`, 'success');
                        } else {
                            showToastIfAvail('Zugang von dieser Stelle entfernt', 'success');
                        }
                    },
                    error: function (xhr) {
                        console.error('Fehler beim Speichern:', xhr.responseText);
                        showToastIfAvail('Fehler beim Speichern der Zugänge', 'error');
                    },
                });
            }
        });
    };
})(window);
