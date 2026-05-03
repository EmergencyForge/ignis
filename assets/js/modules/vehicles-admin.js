/**
 * vehicles-admin.js — Inline-Logik für settings/fahrzeuge/fahrzeuge/index.php.
 *
 * Bündelt:
 *   - DataTable-Init für die Fahrzeug-Tabelle
 *   - Edit-/Copy-Button-Handler (Modal-Befüllung aus Daten-Attributen)
 *   - TZ-Template-Manager (Modal-CRUD für taktische Zeichen)
 *   - Vehicle-Import-Flow (EMD-Sync-Queue mit Polling, Status-States und
 *     pro-Fahrzeug-Aktionen import/merge/overwrite/ignore)
 *
 * Aufruf vom Template:
 *   initVehiclesAdminPage({
 *     basePath:  '/',
 *     tzTplApi:  '/api/vehicles/tz-templates',
 *     importApi: '/api/vehicles/import-handler',
 *   });
 *
 * Mehrere Funktionen müssen wegen inline-`onclick`-Handlern in dynamisch
 * gerenderten Tabellen-Zeilen am `window`-Objekt landen — das Pattern
 * existiert schon im Original-Inline-Script und wird hier 1:1 erhalten.
 */
(function (global) {
    'use strict';

    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function escAttr(str) {
        return String(str ?? '')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;');
    }

    // ── DataTable + Edit/Copy-Buttons ────────────────────────────────

    function bindDataTable() {
        const table = global.$ ? global.$('#table-fahrzeuge') : null;
        if (!table || !table.length) return;
        table.DataTable({
            stateSave:    true,
            paging:       true,
            lengthMenu:   [10, 20, 50],
            pageLength:   20,
            order:        [[0, 'asc']],
            columnDefs:   [{ orderable: false, targets: -1 }],
            language:     global.IgnisDataTableLang
                ? global.IgnisDataTableLang('Fahrzeuge')
                : undefined,
        });
    }

    // Fahrzeug-Dialog (geteilt zwischen Edit, Create und Copy-as-Create).
    // Templates teilen sich Prefix `fahrzeug-`, weil pro Open immer nur eine
    // Dialog-Instanz im DOM ist. tactical-symbol-form-Bindings macht
    // bindTacticalSymbolForm im onOpen.
    function openFahrzeugDialog(opts) {
        const { mode, basePath, data } = opts;
        const isEdit = mode === 'edit';

        global.Dialog.form({
            title:        isEdit ? 'Fahrzeug bearbeiten' : 'Neues Fahrzeug anlegen',
            template:     'fahrzeugFormTemplate',
            formAction:   basePath + (isEdit ? 'settings/fahrzeuge/fahrzeuge/update' : 'settings/fahrzeuge/fahrzeuge/create'),
            hiddenFields: isEdit ? { id: data.id } : {},
            submitLabel:  isEdit ? 'Speichern' : 'Erstellen',
            submitVariant: isEdit ? 'soft-primary' : 'success',
            dangerAction: isEdit ? {
                label:   'Löschen',
                onClick: function () {
                    global.showConfirm('Möchtest du dieses Fahrzeug wirklich löschen?', {
                        danger:      true,
                        confirmText: 'Löschen',
                        title:       'Fahrzeug löschen',
                    }).then((ok) => {
                        if (!ok) return;
                        document.getElementById('fahrzeug-delete-id').value = data.id;
                        document.getElementById('delete-fahrzeug-form').submit();
                    });
                },
            } : null,
            onOpen: function (dlg) {
                const root = dlg.element;
                if (!data) return;

                const setVal = (sel, val) => { const el = root.querySelector(sel); if (el) el.value = val; };
                const setChk = (sel, on)  => { const el = root.querySelector(sel); if (el) el.checked = on; };

                setVal('#fahrzeug-name',         data.name || '');
                setVal('#fahrzeug-kennzeichen',  data.kennzeichen || '');
                setVal('#fahrzeug-veh_typ',      data.type || data.veh_type || '');
                setVal('#fahrzeug-priority',     data.priority || '0');
                setVal('#fahrzeug-identifier',   data.identifier || '');
                setVal('#fahrzeug-rd_type',      data.rd_type || '0');
                setChk('#fahrzeug-active',       data.active == 1 || mode === 'create');
                setVal('#fahrzeug-allowed_jobs', data.allowed_jobs || '');

                setVal('#fahrzeug-grundzeichen', data.tzGrundzeichen || '');
                setVal('#fahrzeug-organisation', data.tzOrganisation || '');
                setVal('#fahrzeug-fachaufgabe',  data.tzFachaufgabe || '');
                setVal('#fahrzeug-einheit',      data.tzEinheit || '');
                setVal('#fahrzeug-symbol',       data.tzSymbol || '');
                setVal('#fahrzeug-typ',          data.tzTyp || '');
                setVal('#fahrzeug-text',         data.tzText || '');
                setVal('#fahrzeug-tz_name',      data.tzName || '');

                if (typeof global.bindTacticalSymbolForm === 'function') {
                    global.bindTacticalSymbolForm(root, 'fahrzeug-', basePath);
                }

                if (data.tzGrundzeichen) {
                    setTimeout(() => root.querySelector('#fahrzeug-preview-btn')?.click(), 150);
                }
            },
        });
    }

    function bindEditButtons(cfg) {
        const basePath = cfg.basePath;

        global.openEditFahrzeugModal = function (btn) {
            openFahrzeugDialog({ mode: 'edit', basePath, data: btn.dataset });
        };

        global.openCreateFahrzeugModal = function () {
            openFahrzeugDialog({ mode: 'create', basePath, data: { active: 1 } });
        };

        document.querySelectorAll('.copy-btn').forEach((button) => {
            button.addEventListener('click', function (e) {
                e.preventDefault();
                const data = Object.assign({}, this.dataset);
                data.identifier = (this.dataset.identifier || '') + '(1)';
                openFahrzeugDialog({ mode: 'create', basePath, data });
            });
        });
    }

    // ── TZ-Template-Manager ──────────────────────────────────────────

    function bindTzTemplateManager(cfg) {
        const TZ_TPL_API = cfg.tzTplApi;

        global.openTzTemplateManager = function () {
            const modal = new global.bootstrap.Modal(document.getElementById('tzTemplateModal'));
            const body  = document.getElementById('tzTemplateModalBody');
            body.innerHTML = '<div class="flex justify-center py-4"><div class="spinner-border" role="status"></div></div>';
            modal.show();
            loadTzTemplateList();
        };

        function loadTzTemplateList() {
            const body = document.getElementById('tzTemplateModalBody');
            fetch(TZ_TPL_API + '?action=list')
                .then((r) => r.json())
                .then((data) => {
                    if (!data.success) throw new Error(data.message);
                    const templates = data.templates;

                    if (templates.length === 0) {
                        body.innerHTML = `
                            <div class="text-center py-4">
                                <div style="font-size:3rem;color:var(--text-dimmed);margin-bottom:1rem;">
                                    <i class="fa-solid fa-shapes"></i>
                                </div>
                                <h5 class="mb-2">Keine Vorlagen vorhanden</h5>
                                <p class="text-[var(--text-dimmed,#818189)]">Erstelle eine Vorlage beim Bearbeiten eines Fahrzeugs über das <i class="fa-solid fa-floppy-disk"></i> Icon neben dem TZ-Formular.</p>
                            </div>
                        `;
                        return;
                    }

                    let html = `
                        <p class="text-[var(--text-dimmed,#818189)] mb-3" style="font-size:var(--fs-sm);">
                            Vorlagen definieren das taktische Zeichen für einen Fahrzeugtyp. Der <strong>Name (tz_name)</strong> bleibt immer individuell pro Fahrzeug.
                        </p>
                        <div class="tz-template-list">
                    `;

                    templates.forEach((t) => {
                        const fields = [t.grundzeichen, t.organisation, t.fachaufgabe, t.einheit, t.symbol].filter(Boolean);
                        const fieldSummary = fields.length > 0
                            ? fields.map((f) => `<span class="ignis-chip ignis-chip--dark" style="font-size:0.65rem;">${escHtml(f)}</span>`).join(' ')
                            : '<span class="text-[var(--text-dimmed,#818189)]">Keine Felder</span>';

                        html += `
                            <div class="intra__tile p-3 mb-2 flex items-center justify-between gap-3" id="tz-tpl-${t.id}">
                                <div class="grow" style="min-width:0;">
                                    <div class="flex items-center gap-2 mb-1">
                                        <strong>${escHtml(t.name)}</strong>
                                        ${t.typ ? `<span class="text-[var(--text-dimmed,#818189)]" style="font-size:var(--fs-sm);">Typ: ${escHtml(t.typ)}</span>` : ''}
                                    </div>
                                    <div class="flex flex-wrap gap-1">${fieldSummary}</div>
                                </div>
                                <div class="flex gap-1 shrink-0">
                                    <button class="ignis-btn ignis-btn--soft-primary ignis-btn--sm" onclick="applyTzTemplateToType(${t.id}, '${escAttr(t.name)}')" title="Auf alle Fahrzeuge eines Typs anwenden">
                                        <i class="fa-solid fa-layer-group mr-1"></i>Anwenden
                                    </button>
                                    <button class="ignis-btn ignis-btn--ghost-danger btn-sm" onclick="deleteTzTemplate(${t.id})" title="Vorlage löschen">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        `;
                    });

                    html += '</div>';
                    body.innerHTML = html;
                })
                .catch((err) => {
                    body.innerHTML = `<div class="ignis-alert ignis-alert--danger">${escHtml(err.message)}</div>`;
                });
        }

        global.applyTzTemplateToType = function (templateId, templateName) {
            if (typeof global.showPrompt === 'function') {
                global.showPrompt('Auf welchen Fahrzeugtyp soll die Vorlage angewendet werden?', templateName, { title: 'Vorlage anwenden' })
                    .then((vehType) => { if (vehType) doApplyTemplate(templateId, vehType); });
            } else {
                const vehType = prompt('Auf welchen Fahrzeugtyp anwenden? (z.B. RTW, HLF20)', templateName);
                if (vehType) doApplyTemplate(templateId, vehType);
            }
        };

        function doApplyTemplate(templateId, vehType) {
            global.showConfirm(`TZ-Vorlage auf ALLE Fahrzeuge vom Typ "${vehType}" anwenden? Der tz_name bleibt individuell.`, {
                confirmText: 'Anwenden',
                title:       'Vorlage anwenden',
            }).then((result) => {
                if (!result) return;
                const fd = new FormData();
                fd.append('action',      'apply_to_type');
                fd.append('template_id', templateId);
                fd.append('veh_type',    vehType);
                fetch(TZ_TPL_API, { method: 'POST', body: fd })
                    .then((r) => r.json())
                    .then((data) => {
                        global.showToast(data.message, data.success ? 'success' : 'error');
                    })
                    .catch((err) => global.showToast(err.message, 'error'));
            });
        }

        global.deleteTzTemplate = function (id) {
            global.showConfirm('Diese Vorlage wirklich löschen?', { danger: true, confirmText: 'Löschen', title: 'TZ-Vorlage löschen' })
                .then((result) => {
                    if (!result) return;
                    const fd = new FormData();
                    fd.append('action', 'delete');
                    fd.append('id',     id);
                    fetch(TZ_TPL_API, { method: 'POST', body: fd })
                        .then((r) => r.json())
                        .then((data) => {
                            if (data.success) {
                                const row = document.getElementById('tz-tpl-' + id);
                                if (row) {
                                    row.style.opacity    = '0';
                                    row.style.transform  = 'translateX(-20px)';
                                    row.style.transition = 'all 0.3s ease';
                                    setTimeout(() => row.remove(), 300);
                                }
                                global.showToast(data.message, 'success');
                            } else {
                                global.showToast(data.message, 'error');
                            }
                        })
                        .catch((err) => global.showToast(err.message, 'error'));
                });
        };
    }

    // ── Vehicle-Import-Flow ──────────────────────────────────────────

    function bindVehicleImport(cfg) {
        const IMPORT_API   = cfg.importApi;
        const rdTypeLabels = { 0: 'Andere', 1: 'RD - Mit NA', 2: 'RD - Ohne NA', 3: 'Feuerwehr' };
        const rdTypeBadges = { 0: 'dark',   1: 'warning',    2: 'success',     3: 'danger'    };

        // Beim Laden: prüfen ob Imports pending sind
        fetch(IMPORT_API + '?action=status')
            .then((r) => r.json())
            .then((data) => {
                if (data.success && data.import_queue_count > 0) {
                    const badge = document.getElementById('importBadge');
                    if (badge) {
                        badge.textContent = data.import_queue_count;
                        badge.classList.remove('hidden');
                    }
                }
            })
            .catch(() => {});

        let importDidChange = false;
        let waitingPollTimer = null;

        const importModal = document.getElementById('vehicleImportModal');
        if (importModal) {
            importModal.addEventListener('hidden.bs.modal', function () {
                if (importDidChange) location.reload();
            });
        }

        global.openVehicleImport = function () {
            importDidChange = false;
            const modal = new global.bootstrap.Modal(importModal);
            const body  = document.getElementById('importModalBody');
            body.innerHTML = '<div class="flex justify-center py-4"><div class="spinner-border" role="status"></div></div>';
            modal.show();

            fetch(IMPORT_API + '?action=status')
                .then((r) => r.json())
                .then((data) => {
                    if (!data.success) throw new Error(data.message);
                    if (data.import_queue_count > 0) {
                        loadImportQueue();
                    } else if (data.request_pending) {
                        showWaitingState();
                    } else {
                        showRequestState();
                    }
                })
                .catch((err) => {
                    body.innerHTML = `<div class="ignis-alert ignis-alert--danger">${escHtml(err.message)}</div>`;
                });
        };

        function showRequestState() {
            document.getElementById('importModalBody').innerHTML = `
                <div class="text-center py-4">
                    <div style="font-size:3rem;color:var(--text-dimmed);margin-bottom:1rem;">
                        <i class="fa-solid fa-satellite-dish"></i>
                    </div>
                    <h5 class="mb-3">Fahrzeugdaten von EMD anfordern</h5>
                    <p class="text-[var(--text-dimmed,#818189)] mb-4">
                        Beim nächsten EMD-Sync werden die Fahrzeugdaten der Leitstelle angefordert.<br>
                        Sobald die Daten eingetroffen sind, können Sie hier jedes Fahrzeug prüfen und importieren.
                    </p>
                    <button class="ignis-btn ignis-btn--soft-primary btn-lg" onclick="requestVehicleImport()">
                        <i class="fa-solid fa-tower-broadcast mr-2"></i>Jetzt anfordern
                    </button>
                </div>
            `;
        }

        function showWaitingState() {
            document.getElementById('importModalBody').innerHTML = `
                <div class="text-center py-4">
                    <div style="font-size:3rem;color:var(--accent);margin-bottom:1rem;">
                        <div class="spinner-grow spinner-grow-sm" role="status"></div>
                        <i class="fa-solid fa-satellite-dish"></i>
                    </div>
                    <h5 class="mb-3">Warte auf EMD-Daten...</h5>
                    <p class="text-[var(--text-dimmed,#818189)] mb-4">
                        Die Anforderung wurde gesendet. Die Fahrzeugdaten werden beim nächsten Sync übermittelt.<br>
                        <small>Dies kann 5–10 Sekunden dauern. Die Ansicht aktualisiert sich automatisch.</small>
                    </p>
                    <button class="ignis-btn ignis-btn--ghost ignis-btn--sm" onclick="openVehicleImport()">
                        <i class="fa-solid fa-rotate mr-1"></i>Erneut prüfen
                    </button>
                </div>
            `;

            if (waitingPollTimer) clearInterval(waitingPollTimer);
            waitingPollTimer = setInterval(() => {
                fetch(IMPORT_API + '?action=status')
                    .then((r) => r.json())
                    .then((data) => {
                        if (data.success && data.import_queue_count > 0) {
                            clearInterval(waitingPollTimer);
                            waitingPollTimer = null;
                            loadImportQueue();
                        }
                    })
                    .catch(() => {});
            }, 3000);

            importModal.addEventListener('hidden.bs.modal', () => {
                if (waitingPollTimer) { clearInterval(waitingPollTimer); waitingPollTimer = null; }
            }, { once: true });
        }

        global.requestVehicleImport = function () {
            const body = document.getElementById('importModalBody');
            body.innerHTML = '<div class="flex justify-center py-4"><div class="spinner-border" role="status"></div></div>';
            const fd = new FormData();
            fd.append('action', 'request');
            fetch(IMPORT_API, { method: 'POST', body: fd })
                .then((r) => r.json())
                .then((data) => {
                    if (data.success) { showWaitingState(); global.showToast(data.message, 'success'); }
                    else { body.innerHTML = `<div class="ignis-alert ignis-alert--danger">${escHtml(data.message)}</div>`; }
                })
                .catch((err) => { body.innerHTML = `<div class="ignis-alert ignis-alert--danger">${escHtml(err.message)}</div>`; });
        };

        function loadImportQueue() {
            fetch(IMPORT_API + '?action=list')
                .then((r) => r.json())
                .then((data) => {
                    if (!data.success) throw new Error(data.message);
                    if (data.count === 0) { showRequestState(); return; }
                    renderVehicleList(data.vehicles);
                })
                .catch((err) => {
                    document.getElementById('importModalBody').innerHTML = `<div class="ignis-alert ignis-alert--danger">${escHtml(err.message)}</div>`;
                });
        }

        function renderVehicleList(vehicles) {
            const newVehicles      = vehicles.filter((v) => !v.existing);
            const existingVehicles = vehicles.filter((v) => v.existing);

            let html = `<div class="flex items-center justify-between mb-3">
                <span class="text-[var(--text-dimmed,#818189)]">${vehicles.length} Fahrzeuge empfangen</span>
                <div class="flex items-center gap-2">
                    <span class="text-[var(--text-dimmed,#818189)]" id="importProgress"></span>
                    <button class="ignis-btn ignis-btn--ghost ignis-btn--sm" onclick="ignoreAllRemaining()" title="Alle verbleibenden Fahrzeuge ignorieren">
                        <i class="fa-solid fa-forward-fast mr-1"></i>Alle ignorieren
                    </button>
                </div>
            </div>`;

            if (newVehicles.length > 0) {
                html += `<h6 class="mb-2" style="color:var(--green);"><i class="fa-solid fa-plus mr-1"></i>Neue Fahrzeuge (${newVehicles.length})</h6>`;
                html += '<div class="import-vehicle-list mb-4">';
                newVehicles.forEach((v, i) => { html += renderVehicleRow(v, i * 40, false); });
                html += '</div>';
            }

            if (existingVehicles.length > 0) {
                html += `<h6 class="mb-2" style="color:var(--warning-text);"><i class="fa-solid fa-exclamation-triangle mr-1"></i>Bereits vorhanden (${existingVehicles.length})</h6>`;
                html += '<div class="import-vehicle-list">';
                existingVehicles.forEach((v, i) => { html += renderVehicleRow(v, (newVehicles.length + i) * 40, true); });
                html += '</div>';
            }

            document.getElementById('importModalBody').innerHTML = html;

            document.querySelectorAll('.import-row').forEach((row) => {
                const delay = parseInt(row.dataset.delay) || 0;
                setTimeout(() => {
                    row.style.opacity   = '1';
                    row.style.transform = 'translateY(0)';
                }, delay);
            });

            updateProgress();
        }

        function renderVehicleRow(v, delay, hasExisting) {
            const e        = v.existing;
            const rdBadge  = `<span class="badge text-bg-${rdTypeBadges[v.rd_type] || 'dark'}" style="font-size:var(--fs-xs);">${rdTypeLabels[v.rd_type] || 'Andere'}</span>`;
            const deptInfo = v.department ? `<span style="font-size:var(--fs-xs);color:var(--text-dimmed);"><i class="fa-solid fa-building mr-1"></i>${escHtml(v.department)}</span>` : '';

            let existingInfo = '';
            if (hasExisting && e) {
                existingInfo = `
                    <div class="mt-2 p-2 rounded" style="background:rgba(255,255,255,0.03);font-size:var(--fs-xs);border:1px solid rgba(255,255,255,0.06);">
                        <span class="text-[var(--text-dimmed,#818189)]">Bestehendes Fahrzeug:</span>
                        <strong>${escHtml(e.name)}</strong> (${escHtml(e.veh_type || '-')})
                        — ${escHtml(e.identifier || '-')}
                        <span class="badge text-bg-${rdTypeBadges[e.rd_type] || 'dark'}" style="font-size:0.6rem;">${rdTypeLabels[e.rd_type] || '?'}</span>
                    </div>
                `;
            }

            let actions = '';
            if (hasExisting && e) {
                actions = `
                    <div class="flex gap-1 shrink-0">
                        <button class="ignis-btn ignis-btn--ghost ignis-btn--sm" onclick="importAction(${v.id}, 'ignore')" title="Ignorieren">
                            <i class="fa-solid fa-forward"></i>
                        </button>
                        <button class="ignis-btn ignis-btn--soft-warning ignis-btn--sm" data-import-action="merge" onclick="importAction(${v.id}, 'merge', ${e.id})" title="Zusammenführen (nur leere Felder füllen)">
                            <i class="fa-solid fa-code-merge"></i>
                        </button>
                        <button class="ignis-btn ignis-btn--soft-danger ignis-btn--sm" data-import-action="overwrite" onclick="importAction(${v.id}, 'overwrite', ${e.id})" title="Überschreiben">
                            <i class="fa-solid fa-rotate"></i>
                        </button>
                    </div>
                `;
            } else {
                actions = `
                    <div class="flex gap-1 shrink-0">
                        <button class="ignis-btn ignis-btn--ghost ignis-btn--sm" onclick="importAction(${v.id}, 'ignore')" title="Ignorieren">
                            <i class="fa-solid fa-forward"></i>
                        </button>
                        <button class="ignis-btn ignis-btn--success ignis-btn--sm" data-import-action="import" onclick="importAction(${v.id}, 'import')" title="Importieren">
                            <i class="fa-solid fa-check"></i> Import
                        </button>
                    </div>
                `;
            }

            return `
                <div class="import-row intra__tile p-3 mb-2" id="import-row-${v.id}" data-delay="${delay}"
                     style="opacity:0;transform:translateY(10px);transition:all 0.3s ease ${delay}ms;">
                    <div class="flex items-start justify-between gap-3">
                        <div class="grow" style="min-width:0;">
                            <div class="flex items-center gap-2 mb-1">
                                <strong style="font-size:var(--fs-md);">${escHtml(v.name)}</strong>
                                ${rdBadge}
                            </div>
                            <div class="flex flex-wrap gap-3 mb-1" style="font-size:var(--fs-sm);color:var(--text-dimmed);">
                                <span>${escHtml(v.valuelong || '-')}</span>
                                <span>Typ: <strong>${escHtml(v.veh_type || '-')}</strong></span>
                                <span>ID: ${escHtml(v.identifier || '-')}</span>
                                ${v.funkkanal ? `<span>Kanal: ${escHtml(v.funkkanal)}</span>` : ''}
                            </div>
                            ${deptInfo}
                            ${existingInfo}
                        </div>
                        ${actions}
                    </div>
                    <div class="import-row-edit hidden mt-2 pt-2" id="import-edit-${v.id}" style="border-top:1px solid rgba(255,255,255,0.06);">
                        <div class="flex flex-wrap -mx-3 g-2" style="font-size:var(--fs-sm);">
                            <div class="w-4/12 px-3">
                                <label class="ignis-field__label mb-0 text-[var(--text-dimmed,#818189)]">Typ</label>
                                <input type="text" class="ignis-input ignis-input--sm" id="imp-veh_type-${v.id}" value="${escAttr(v.veh_type || '')}">
                            </div>
                            <div class="w-4/12 px-3">
                                <label class="ignis-field__label mb-0 text-[var(--text-dimmed,#818189)]">RD-Typ</label>
                                <select class="form-select form-select-sm" data-custom-dropdown="true" id="imp-rd_type-${v.id}">
                                    <option value="0" ${v.rd_type==0?'selected':''}>Andere</option>
                                    <option value="1" ${v.rd_type==1?'selected':''}>RD - Mit NA</option>
                                    <option value="2" ${v.rd_type==2?'selected':''}>RD - Ohne NA</option>
                                    <option value="3" ${v.rd_type==3?'selected':''}>Feuerwehr</option>
                                </select>
                            </div>
                            <div class="w-4/12 px-3">
                                <label class="ignis-field__label mb-0 text-[var(--text-dimmed,#818189)]">Erlaubte Jobs</label>
                                <input type="text" class="ignis-input ignis-input--sm" id="imp-allowed_jobs-${v.id}" value="${escAttr(v.job || '')}">
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        global.importAction = function (queueId, action, existingId) {
            const row = document.getElementById('import-row-' + queueId);
            if (!row) return;

            if (action === 'ignore') {
                executeImportAction(queueId, action);
                return;
            }

            const editArea = document.getElementById('import-edit-' + queueId);
            if (editArea.classList.contains('hidden')) {
                editArea.classList.remove('hidden');
            }

            const activeAction = row.dataset.activeAction;
            if (activeAction === action) {
                executeImportAction(queueId, action, existingId);
                return;
            }

            row.dataset.activeAction = action;

            const labels = { import: 'Import',     overwrite: 'Überschreiben', merge: 'Zusammenführen' };
            const icons  = { import: 'check',      overwrite: 'rotate',         merge: 'code-merge' };
            const styles = { import: 'btn-success', overwrite: 'btn-soft-danger', merge: 'btn-soft-warning' };

            row.querySelectorAll('[data-import-action]').forEach((btn) => {
                const a = btn.dataset.importAction;
                btn.className = `btn ${styles[a]} btn-sm`;
                btn.innerHTML = `<i class="fa-solid fa-${icons[a]}"></i>`;
            });

            const activeBtn = row.querySelector(`[data-import-action="${action}"]`);
            if (activeBtn) {
                activeBtn.className = `btn ${styles[action]} btn-sm`;
                activeBtn.innerHTML = `<i class="fa-solid fa-check mr-1"></i>${labels[action]}`;
            }
        };

        function executeImportAction(queueId, action, existingId) {
            const row = document.getElementById('import-row-' + queueId);
            if (!row) return;

            row.querySelectorAll('button').forEach((b) => (b.disabled = true));

            const fd = new FormData();
            fd.append('action',   action);
            fd.append('queue_id', queueId);
            if (existingId) fd.append('existing_id', existingId);

            const vehType     = document.getElementById('imp-veh_type-' + queueId);
            const rdType      = document.getElementById('imp-rd_type-' + queueId);
            const allowedJobs = document.getElementById('imp-allowed_jobs-' + queueId);
            if (vehType)     fd.append('veh_type',     vehType.value);
            if (rdType)      fd.append('rd_type',      rdType.value);
            if (allowedJobs) fd.append('allowed_jobs', allowedJobs.value);

            fetch(IMPORT_API, { method: 'POST', body: fd })
                .then((r) => r.json())
                .then((data) => {
                    if (data.success) {
                        row.style.opacity     = '0';
                        row.style.transform   = 'translateX(-20px) scale(0.97)';
                        row.style.maxHeight   = row.offsetHeight + 'px';
                        row.style.overflow    = 'hidden';
                        setTimeout(() => {
                            row.style.maxHeight    = '0';
                            row.style.padding      = '0';
                            row.style.marginBottom = '0';
                            row.style.borderWidth  = '0';
                        }, 200);
                        setTimeout(() => { row.remove(); updateProgress(); }, 500);

                        if (action !== 'ignore') importDidChange = true;
                        const actionLabel = { import: 'importiert', overwrite: 'überschrieben', merge: 'zusammengeführt', ignore: 'ignoriert' };
                        global.showToast(data.message || `Fahrzeug ${actionLabel[action] || 'verarbeitet'}`, action === 'ignore' ? 'info' : 'success');
                    } else {
                        global.showToast(data.message, 'error');
                        row.querySelectorAll('button').forEach((b) => (b.disabled = false));
                    }
                })
                .catch((err) => {
                    global.showToast(err.message, 'error');
                    row.querySelectorAll('button').forEach((b) => (b.disabled = false));
                });
        }

        function updateProgress() {
            const remaining = document.querySelectorAll('.import-row').length;
            const el = document.getElementById('importProgress');
            if (el) el.textContent = remaining > 0 ? `${remaining} verbleibend` : '';
            if (remaining === 0) {
                const body = document.getElementById('importModalBody');
                body.innerHTML = `
                    <div class="text-center py-4">
                        <div style="font-size:3rem;color:var(--green);margin-bottom:1rem;">
                            <i class="fa-solid fa-check-circle"></i>
                        </div>
                        <h5>Import abgeschlossen</h5>
                        <p class="text-[var(--text-dimmed,#818189)]">Alle Fahrzeuge wurden verarbeitet.</p>
                        <button class="ignis-btn ignis-btn--soft-primary" onclick="location.reload()">Seite neu laden</button>
                    </div>
                `;
                const badge = document.getElementById('importBadge');
                if (badge) badge.classList.add('hidden');
            }
        }

        global.ignoreAllRemaining = function () {
            global.showConfirm('Alle verbleibenden Fahrzeuge ignorieren?', {
                danger:      false,
                confirmText: 'Alle ignorieren',
                title:       'Restliche ignorieren',
            }).then((ok) => {
                if (!ok) return;
                const rows = document.querySelectorAll('.import-row');
                if (rows.length === 0) return;

                const ids = [];
                rows.forEach((row) => {
                    const id = row.id.replace('import-row-', '');
                    if (id) ids.push(parseInt(id));
                });

                rows.forEach((row) => {
                    row.querySelectorAll('button').forEach((b) => (b.disabled = true));
                    row.style.opacity = '0.5';
                });

                const promises = ids.map((queueId) => {
                    const fd = new FormData();
                    fd.append('action',   'ignore');
                    fd.append('queue_id', queueId);
                    return fetch(IMPORT_API, { method: 'POST', body: fd }).then((r) => r.json());
                });

                Promise.all(promises).then(() => {
                    global.showToast(ids.length + ' Fahrzeuge ignoriert', 'info');
                    rows.forEach((row) => row.remove());
                    updateProgress();
                }).catch(() => {
                    global.showToast('Fehler beim Ignorieren', 'error');
                    rows.forEach((row) => {
                        row.style.opacity = '1';
                        row.querySelectorAll('button').forEach((b) => (b.disabled = false));
                    });
                });
            });
        };
    }

    // ── Public Entry-Point ───────────────────────────────────────────

    global.initVehiclesAdminPage = function (config) {
        bindDataTable();
        bindEditButtons(config);
        bindTzTemplateManager(config);
        bindVehicleImport(config);
    };
})(window);
