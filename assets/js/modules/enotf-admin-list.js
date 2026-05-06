/**
 * enotf-admin-list.js — Inline-Script aus templates/enotf/admin/list.php
 * extrahiert. Verhalten 1:1 erhalten — DataTable-Config, QM-Modals,
 * QM-Log-Modal, Bulk-Delete-Mehrstufiger-Flow.
 *
 * Aufruf vom Template:
 *   initEnotfAdminListPage({
 *       basePath:    '<?= BASE_PATH ?>',
 *       qmActionsApi:'<?= BASE_PATH ?>enotf/admin/qm-actions-modal',
 *       qmLogApi:    '<?= BASE_PATH ?>enotf/admin/qm-log-modal',
 *       bulkDeleteApi:'<?= BASE_PATH ?>api/enotf/bulk-delete-empty',
 *   });
 *
 * Modals (qmActionsModal, qmLogModal, bulkDeleteModal) bleiben Bootstrap;
 * deren Migration laeuft in einem spaeteren A20.1-eNOTF-Block.
 */
(function (global) {
    'use strict';

    const $ = global.jQuery || global.$;

    global.initEnotfAdminListPage = function (cfg) {
        const QM_ACTIONS_API   = cfg.qmActionsApi   || (cfg.basePath + 'enotf/admin/qm-actions-modal');
        const QM_LOG_API       = cfg.qmLogApi       || (cfg.basePath + 'enotf/admin/qm-log-modal');
        const BULK_DELETE_API  = cfg.bulkDeleteApi  || (cfg.basePath + 'api/enotf/bulk-delete-empty');

        $(document).ready(function () {
            const table = $('#table-protokoll').DataTable({
                stateSave:  true,
                paging:     true,
                lengthMenu: [10, 20, 50, 100],
                pageLength: 20,
                order:      [[2, 'desc']],
                columnDefs: [{ orderable: false, targets: -1 }],
                language:   global.IgnisDataTableLang('Protokolle'),
            });

            // ── QM-Actions-Modal ─────────────────────────────────────
            global.openQMActions = function (id, enr, patname) {
                const modal = new global.bootstrap.Modal(document.getElementById('qmActionsModal'));
                document.getElementById('qmActionsModalLabel').textContent = `QM-Funktionen [#${enr}] ${patname}`;
                document.getElementById('qmActionsContent').innerHTML = `
                    <div class="flex justify-center">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Laden...</span>
                        </div>
                    </div>`;
                modal.show();

                fetch(`${QM_ACTIONS_API}?id=${id}`)
                    .then((r) => r.text())
                    .then((html) => { document.getElementById('qmActionsContent').innerHTML = html; })
                    .catch((err) => {
                        document.getElementById('qmActionsContent').innerHTML = `
                            <div class="ignis-alert ignis-alert--danger">
                                Fehler beim Laden der QM-Aktionen: ${err.message}
                            </div>`;
                    });
            };

            // ── QM-Log-Modal ─────────────────────────────────────────
            global.openQMLog = function (id, enr, patname) {
                const modal = new global.bootstrap.Modal(document.getElementById('qmLogModal'));
                document.getElementById('qmLogModalLabel').textContent = `QM-Log [#${enr}] ${patname}`;
                document.getElementById('qmLogContent').innerHTML = `
                    <div class="flex justify-center">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Laden...</span>
                        </div>
                    </div>`;
                modal.show();

                fetch(`${QM_LOG_API}?id=${id}`)
                    .then((r) => r.text())
                    .then((html) => { document.getElementById('qmLogContent').innerHTML = html; })
                    .catch((err) => {
                        document.getElementById('qmLogContent').innerHTML = `
                            <div class="ignis-alert ignis-alert--danger">
                                Fehler beim Laden des QM-Logs: ${err.message}
                            </div>`;
                    });
            };

            // ── QM-Actions Form-Submit (delegated, weil Form via AJAX nachgeladen) ──
            $(document).on('submit', '#qmActionsForm', function (e) {
                e.preventDefault();
                const formData    = new FormData(this);
                const submitBtn   = this.querySelector('input[type="submit"]');
                const originalText = submitBtn.value;

                submitBtn.value    = 'Speichere...';
                submitBtn.disabled = true;

                fetch(this.action, { method: 'POST', body: formData })
                    .then((r) => r.json())
                    .then((data) => {
                        if (data.success) {
                            global.bootstrap.Modal.getInstance(document.getElementById('qmActionsModal')).hide();
                            location.reload();
                        } else {
                            global.showAlert('Fehler beim Speichern: ' + (data.message || 'Unbekannter Fehler'), {
                                type: 'error', title: 'Fehler',
                            });
                        }
                    })
                    .catch((err) => {
                        global.showAlert('Fehler beim Speichern: ' + err.message, { type: 'error', title: 'Fehler' });
                    })
                    .finally(() => {
                        submitBtn.value    = originalText;
                        submitBtn.disabled = false;
                    });
            });
        });

        // ── Bulk-Delete-Modal (Mehrstufiger Flow) ────────────────────

        global.showBulkDeleteModal = function () {
            const modal = new global.bootstrap.Modal(document.getElementById('bulkDeleteModal'));

            document.getElementById('bulkDeleteContent').innerHTML = `
                <div class="flex justify-center">
                    <div class="spinner-border" role="status">
                        <span class="sr-only">Laden...</span>
                    </div>
                </div>`;
            document.getElementById('bulkDeleteFooter').style.display = 'none';

            modal.show();

            fetch(BULK_DELETE_API)
                .then((r) => r.json())
                .then((data) => {
                    if (data.success && data.fields) {
                        let fieldsHtml = '';
                        for (const [key, label] of Object.entries(data.fields)) {
                            const checked = key === 'patname' ? 'checked' : '';
                            fieldsHtml += `
                                <div class="ignis-checkbox">
                                    <input class="bulk-field-checkbox" type="checkbox" value="${key}" id="field_${key}" ${checked}>
                                    <label for="field_${key}">${label}</label>
                                </div>`;
                        }

                        document.getElementById('bulkDeleteContent').innerHTML = `
                            <div class="ignis-alert ignis-alert--info">
                                <i class="fa-solid fa-circle-info"></i>
                                <strong>Felder auswählen</strong>
                                <p class="mb-0 mt-2">Wählen Sie die Felder aus, die leer sein müssen, damit ein Protokoll gelöscht wird.</p>
                            </div>
                            <form id="bulkDeleteFieldsForm">
                                <div class="mb-3">
                                    <label class="ignis-field__label fw-bold">Zeitraum:</label>
                                    <select class="form-select" id="timePeriod">
                                        <option value="7">Letzte 7 Tage</option>
                                        <option value="30" selected>Letzte 30 Tage</option>
                                        <option value="90">Letzte 90 Tage</option>
                                        <option value="180">Letzte 180 Tage</option>
                                        <option value="all">Insgesamt (alle Protokolle)</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="ignis-field__label fw-bold">Leere Felder (ALLE müssen leer sein):</label>
                                    ${fieldsHtml}
                                </div>
                                <button type="button" class="ignis-btn ignis-btn--soft-primary" onclick="previewBulkDelete()">
                                    <i class="fa-solid fa-search"></i> Vorschau anzeigen
                                </button>
                            </form>`;
                    } else {
                        document.getElementById('bulkDeleteContent').innerHTML = `
                            <div class="ignis-alert ignis-alert--danger">
                                <i class="fa-solid fa-exclamation-circle"></i>
                                Fehler: ${data.message || 'Unbekannter Fehler'}
                            </div>`;
                    }
                })
                .catch((err) => {
                    document.getElementById('bulkDeleteContent').innerHTML = `
                        <div class="ignis-alert ignis-alert--danger">
                            <i class="fa-solid fa-exclamation-circle"></i>
                            Fehler: ${err.message}
                        </div>`;
                });
        };

        global.previewBulkDelete = function () {
            const checkboxes     = document.querySelectorAll('.bulk-field-checkbox:checked');
            const selectedFields = Array.from(checkboxes).map((cb) => cb.value);
            const timePeriod     = document.getElementById('timePeriod').value;

            if (selectedFields.length === 0) {
                global.showToast('Bitte wählen Sie mindestens ein Feld aus.', 'warning');
                return;
            }

            document.getElementById('bulkDeleteContent').innerHTML = `
                <div class="flex justify-center">
                    <div class="spinner-border" role="status">
                        <span class="sr-only">Lade Vorschau...</span>
                    </div>
                </div>`;

            const formData = new FormData();
            selectedFields.forEach((field) => formData.append('fields[]', field));
            formData.append('preview', '1');
            formData.append('timePeriod', timePeriod);

            fetch(BULK_DELETE_API, { method: 'POST', body: formData })
                .then((r) => r.json())
                .then((data) => {
                    if (!data.success) {
                        document.getElementById('bulkDeleteContent').innerHTML = `
                            <div class="ignis-alert ignis-alert--danger">
                                <i class="fa-solid fa-exclamation-circle"></i>
                                Fehler: ${data.message || 'Unbekannter Fehler'}
                            </div>
                            <button type="button" class="ignis-btn ignis-btn--ghost" onclick="showBulkDeleteModal()">
                                <i class="fa-solid fa-arrow-left"></i> Zurück
                            </button>`;
                        return;
                    }

                    if (data.count === 0) {
                        document.getElementById('bulkDeleteContent').innerHTML = `
                            <div class="ignis-alert ignis-alert--info">
                                <i class="fa-solid fa-circle-info"></i>
                                <strong>Keine leeren Protokolle gefunden</strong>
                                <p class="mb-0 mt-2">Es wurden keine Protokolle gefunden, die alle ausgewählten Kriterien erfüllen.</p>
                            </div>
                            <button type="button" class="ignis-btn ignis-btn--ghost" onclick="showBulkDeleteModal()">
                                <i class="fa-solid fa-arrow-left"></i> Zurück
                            </button>`;
                        return;
                    }

                    const protocolsList = data.protocols.map((p) => {
                        const date = new Date(p.sendezeit);
                        const dateStr = date.toLocaleDateString('de-DE') + ' ' +
                            date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
                        return `
                            <tr>
                                <td>${p.enr}</td>
                                <td>${p.patname || '<em>Unbekannt</em>'}</td>
                                <td>${dateStr}</td>
                                <td>${p.pfname || ''}</td>
                            </tr>`;
                    }).join('');

                    document.getElementById('bulkDeleteContent').innerHTML = `
                        <div class="ignis-alert ignis-alert--warning">
                            <i class="fa-solid fa-exclamation-triangle"></i>
                            <strong>Achtung!</strong>
                            <p class="mb-0 mt-2">Es wurden <strong>${data.count} leere Protokolle</strong> gefunden.</p>
                            <p class="mb-0 mt-2"><small>Leere Felder: ${data.selectedFieldsLabel}</small></p>
                        </div>
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-sm table-striped">
                                <thead class="sticky-top bg-[rgba(0,0,0,0.3)]">
                                    <tr>
                                        <th>Einsatznummer</th>
                                        <th>Patient</th>
                                        <th>Angelegt am</th>
                                        <th>Protokollant</th>
                                    </tr>
                                </thead>
                                <tbody>${protocolsList}</tbody>
                            </table>
                        </div>`;
                    document.getElementById('bulkDeleteFooter').style.display = 'flex';
                    global.bulkDeleteSelectedFields = selectedFields;
                    global.bulkDeleteTimePeriod     = timePeriod;
                })
                .catch((err) => {
                    document.getElementById('bulkDeleteContent').innerHTML = `
                        <div class="ignis-alert ignis-alert--danger">
                            <i class="fa-solid fa-exclamation-circle"></i>
                            Fehler: ${err.message}
                        </div>
                        <button type="button" class="ignis-btn ignis-btn--ghost" onclick="showBulkDeleteModal()">
                            <i class="fa-solid fa-arrow-left"></i> Zurück
                        </button>`;
                });
        };

        global.executeBulkDelete = function () {
            const deleteButton = event.target;
            const originalText = deleteButton.innerHTML;

            if (!global.bulkDeleteSelectedFields || global.bulkDeleteSelectedFields.length === 0) {
                global.showToast('Keine Felder ausgewählt', 'warning');
                return;
            }

            deleteButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Lösche...';
            deleteButton.disabled  = true;

            const formData = new FormData();
            global.bulkDeleteSelectedFields.forEach((field) => formData.append('fields[]', field));
            formData.append('timePeriod', global.bulkDeleteTimePeriod || '30');

            fetch(BULK_DELETE_API, { method: 'POST', body: formData })
                .then((r) => r.json())
                .then((data) => {
                    if (data.success) {
                        document.getElementById('bulkDeleteContent').innerHTML = `
                            <div class="ignis-alert ignis-alert--success">
                                <i class="fa-solid fa-check-circle"></i>
                                <strong>Erfolgreich!</strong>
                                <p class="mb-0 mt-2">${data.deleted} Protokoll(e) wurden erfolgreich gelöscht.</p>
                            </div>`;
                        document.getElementById('bulkDeleteFooter').style.display = 'none';
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        document.getElementById('bulkDeleteContent').innerHTML = `
                            <div class="ignis-alert ignis-alert--danger">
                                <i class="fa-solid fa-exclamation-circle"></i>
                                Fehler beim Löschen: ${data.message || 'Unbekannter Fehler'}
                            </div>`;
                        deleteButton.innerHTML = originalText;
                        deleteButton.disabled  = false;
                    }
                })
                .catch((err) => {
                    document.getElementById('bulkDeleteContent').innerHTML = `
                        <div class="ignis-alert ignis-alert--danger">
                            <i class="fa-solid fa-exclamation-circle"></i>
                            Fehler beim Löschen: ${err.message}
                        </div>`;
                    deleteButton.innerHTML = originalText;
                    deleteButton.disabled  = false;
                });
        };
    };
})(window);
