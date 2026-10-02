/**
 * enotf-admin-list.js — QM-Dialoge und das Löschen leerer Protokolle auf
 * der eNOTF-Prüfliste (plugins/enotf/templates/enotf/admin/list.php).
 * Sortieren, Suchen und Blättern macht der Server (App\Support\ListQuery).
 *
 * Aufruf vom Template:
 *   initEnotfAdminListPage({
 *       qmActionsApi: '<?= BASE_PATH ?>enotf/admin/qm-actions-modal',
 *       qmLogApi:     '<?= BASE_PATH ?>enotf/admin/qm-log-modal',
 *       bulkDeleteApi:'<?= BASE_PATH ?>api/enotf/bulk-delete-empty',
 *   });
 *
 * Die Knöpfe einer Zeile tragen data-enotf-qm="actions" oder "log" mit
 * data-id, data-enr und data-patname. Die Dialoge (qmActionsModal,
 * qmLogModal, bulkDeleteModal) liegen als [data-dialog-source] im Template
 * und öffnen über Dialog.openElement. Das Markup des Löschablaufs ruft
 * showBulkDeleteModal, previewBulkDelete und executeBulkDelete auf, deshalb
 * bleiben die drei global.
 */
(function (global) {
    'use strict';

    // Antworten des Servers landen per innerHTML im Dialog, also escapen.
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[c]);

    const loadingSkeleton = (label) => `
        <div class="twplus-skeleton" role="status" aria-label="${esc(label)}">
            <div class="twplus-skeleton__line twplus-skeleton__line--short"></div>
            <div class="twplus-skeleton__line"></div>
            <div class="twplus-skeleton__line"></div>
        </div>`;

    const errorAlert = (text) => `
        <div class="ignis-alert ignis-alert--danger">
            <i class="fa-solid fa-exclamation-circle"></i> ${esc(text)}
        </div>`;

    const backButton = `
        <button type="button" class="ignis-btn ignis-btn--ghost" onclick="showBulkDeleteModal()">
            <i class="fa-solid fa-arrow-left"></i> Zurück
        </button>`;

    const setContent = (id, html) => { document.getElementById(id).innerHTML = html; };

    global.initEnotfAdminListPage = function (cfg) {
        // ── QM-Aktionen und QM-Log: das Markup kommt vom Server ──────
        const qmDialogs = {
            actions: { id: 'qmActions', title: 'QM-Funktionen', url: cfg.qmActionsApi, loading: 'QM-Aktionen werden geladen', failed: 'Fehler beim Laden der QM-Aktionen: ' },
            log:     { id: 'qmLog',     title: 'QM-Log',        url: cfg.qmLogApi,     loading: 'QM-Log wird geladen',       failed: 'Fehler beim Laden des QM-Logs: ' },
        };

        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-enotf-qm]');
            const dialog = button ? qmDialogs[button.dataset.enotfQm] : null;
            if (!dialog) return;

            const { id, enr, patname } = button.dataset;
            document.getElementById(dialog.id + 'ModalLabel').textContent = `${dialog.title} [#${enr}] ${patname}`;
            setContent(dialog.id + 'Content', loadingSkeleton(dialog.loading));
            global.Dialog.openElement('#' + dialog.id + 'Modal');

            fetch(`${dialog.url}?id=${encodeURIComponent(id)}`)
                .then((r) => r.text())
                .then((html) => setContent(dialog.id + 'Content', html))
                .catch((err) => setContent(dialog.id + 'Content', errorAlert(dialog.failed + err.message)));
        });

        // Das Formular der QM-Aktionen kommt nachgeladen, deshalb am document.
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (form.id !== 'qmActionsForm') return;
            event.preventDefault();

            const submitBtn    = form.querySelector('input[type="submit"]');
            const originalText = submitBtn.value;
            submitBtn.value    = 'Speichere...';
            submitBtn.disabled = true;

            fetch(form.action, { method: 'POST', body: new FormData(form) })
                .then((r) => r.json())
                .then((data) => {
                    if (data.success) {
                        global.Dialog.closeElement('#qmActionsModal');
                        location.reload();
                    } else {
                        global.showAlert('Fehler beim Speichern: ' + (data.message || 'Unbekannter Fehler'), { type: 'error', title: 'Fehler' });
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

        // ── Leere Protokolle löschen: Felder, Vorschau, Ergebnis ─────
        const showFooter = (visible) => { document.getElementById('bulkDeleteFooter').style.display = visible ? 'flex' : 'none'; };

        global.showBulkDeleteModal = function () {
            setContent('bulkDeleteContent', loadingSkeleton('Felder werden geladen'));
            showFooter(false);
            global.Dialog.openElement('#bulkDeleteModal');

            fetch(cfg.bulkDeleteApi)
                .then((r) => r.json())
                .then((data) => {
                    if (!data.success || !data.fields) {
                        setContent('bulkDeleteContent', errorAlert('Fehler: ' + (data.message || 'Unbekannter Fehler')));
                        return;
                    }

                    const fieldsHtml = Object.entries(data.fields).map(([key, label]) => `
                        <div class="ignis-checkbox">
                            <input class="bulk-field-checkbox" type="checkbox" value="${esc(key)}" id="field_${esc(key)}"${key === 'patname' ? ' checked' : ''}>
                            <label for="field_${esc(key)}">${esc(label)}</label>
                        </div>`).join('');

                    setContent('bulkDeleteContent', `
                        <div class="ignis-alert ignis-alert--info">
                            <i class="fa-solid fa-circle-info"></i>
                            <strong>Felder auswählen</strong>
                            <p class="mb-0 mt-2">Wählen Sie die Felder aus, die leer sein müssen, damit ein Protokoll gelöscht wird.</p>
                        </div>
                        <form id="bulkDeleteFieldsForm">
                            <div class="mb-3">
                                <label class="ignis-field__label font-bold" for="timePeriod">Zeitraum:</label>
                                <select class="ignis-input" data-custom-dropdown="true" id="timePeriod">
                                    <option value="7">Letzte 7 Tage</option>
                                    <option value="30" selected>Letzte 30 Tage</option>
                                    <option value="90">Letzte 90 Tage</option>
                                    <option value="180">Letzte 180 Tage</option>
                                    <option value="all">Insgesamt (alle Protokolle)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <span class="ignis-field__label font-bold">Leere Felder (ALLE müssen leer sein):</span>
                                ${fieldsHtml}
                            </div>
                            <button type="button" class="ignis-btn ignis-btn--secondary" onclick="previewBulkDelete()">
                                <i class="fa-solid fa-search"></i> Vorschau anzeigen
                            </button>
                        </form>`);
                })
                .catch((err) => setContent('bulkDeleteContent', errorAlert('Fehler: ' + err.message)));
        };

        global.previewBulkDelete = function () {
            const selectedFields = Array.from(document.querySelectorAll('.bulk-field-checkbox:checked')).map((cb) => cb.value);
            const timePeriod     = document.getElementById('timePeriod').value;

            if (selectedFields.length === 0) {
                global.showToast('Bitte wählen Sie mindestens ein Feld aus.', 'warning');
                return;
            }

            setContent('bulkDeleteContent', loadingSkeleton('Vorschau wird geladen'));

            const formData = new FormData();
            selectedFields.forEach((field) => formData.append('fields[]', field));
            formData.append('preview', '1');
            formData.append('timePeriod', timePeriod);

            fetch(cfg.bulkDeleteApi, { method: 'POST', body: formData })
                .then((r) => r.json())
                .then((data) => {
                    if (!data.success) {
                        setContent('bulkDeleteContent', errorAlert('Fehler: ' + (data.message || 'Unbekannter Fehler')) + backButton);
                        return;
                    }

                    if (data.count === 0) {
                        setContent('bulkDeleteContent', `
                            <div class="ignis-alert ignis-alert--info">
                                <i class="fa-solid fa-circle-info"></i>
                                <strong>Keine leeren Protokolle gefunden</strong>
                                <p class="mb-0 mt-2">Es wurden keine Protokolle gefunden, die alle ausgewählten Kriterien erfüllen.</p>
                            </div>${backButton}`);
                        return;
                    }

                    const rows = data.protocols.map((p) => {
                        const date = new Date(p.sendezeit);
                        const dateStr = date.toLocaleDateString('de-DE') + ' ' +
                            date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
                        return `
                            <tr>
                                <td>${esc(p.enr)}</td>
                                <td>${p.patname ? esc(p.patname) : '<em>Unbekannt</em>'}</td>
                                <td>${esc(dateStr)}</td>
                                <td>${esc(p.pfname)}</td>
                            </tr>`;
                    }).join('');

                    setContent('bulkDeleteContent', `
                        <div class="ignis-alert ignis-alert--warn">
                            <i class="fa-solid fa-exclamation-triangle"></i>
                            <strong>Achtung!</strong>
                            <p class="mb-0 mt-2">Es wurden <strong>${esc(data.count)} leere Protokolle</strong> gefunden.</p>
                            <p class="mb-0 mt-2"><small>Leere Felder: ${esc(data.selectedFieldsLabel)}</small></p>
                        </div>
                        <div class="overflow-x-auto" style="max-height: 400px; overflow-y: auto;">
                            <table class="ignis-table">
                                <thead>
                                    <tr>
                                        <th>Einsatznummer</th>
                                        <th>Patient</th>
                                        <th>Angelegt am</th>
                                        <th>Protokollant</th>
                                    </tr>
                                </thead>
                                <tbody>${rows}</tbody>
                            </table>
                        </div>`);
                    showFooter(true);
                    global.bulkDeleteSelectedFields = selectedFields;
                    global.bulkDeleteTimePeriod     = timePeriod;
                })
                .catch((err) => setContent('bulkDeleteContent', errorAlert('Fehler: ' + err.message) + backButton));
        };

        // Bekommt den Knopf aus onclick="executeBulkDelete(this)".
        global.executeBulkDelete = function (deleteButton) {
            const originalText = deleteButton.innerHTML;

            if (!global.bulkDeleteSelectedFields || global.bulkDeleteSelectedFields.length === 0) {
                global.showToast('Keine Felder ausgewählt', 'warning');
                return;
            }

            deleteButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1" aria-hidden="true"></i> Lösche...';
            deleteButton.disabled  = true;

            const formData = new FormData();
            global.bulkDeleteSelectedFields.forEach((field) => formData.append('fields[]', field));
            formData.append('timePeriod', global.bulkDeleteTimePeriod || '30');

            const failed = (message) => {
                setContent('bulkDeleteContent', errorAlert('Fehler beim Löschen: ' + message));
                deleteButton.innerHTML = originalText;
                deleteButton.disabled  = false;
            };

            fetch(cfg.bulkDeleteApi, { method: 'POST', body: formData })
                .then((r) => r.json())
                .then((data) => {
                    if (!data.success) {
                        failed(data.message || 'Unbekannter Fehler');
                        return;
                    }
                    setContent('bulkDeleteContent', `
                        <div class="ignis-alert ignis-alert--ok">
                            <i class="fa-solid fa-check-circle"></i>
                            <strong>Erfolgreich!</strong>
                            <p class="mb-0 mt-2">${esc(data.deleted)} Protokoll(e) wurden erfolgreich gelöscht.</p>
                        </div>`);
                    showFooter(false);
                    setTimeout(() => location.reload(), 2000);
                })
                .catch((err) => failed(err.message));
        };
    };
})(window);
