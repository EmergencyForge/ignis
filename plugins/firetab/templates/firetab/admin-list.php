<?php
/**
 * View: QM-Übersicht aller Einsatzprotokolle (Admin)
 *
 * Sortierung, Suche und Seiten laufen über den Server (App\Support\ListQuery,
 * FiretabController::adminList). Die Liste ist ein Arbeitsbereich
 * (assets/js/ui/workbench.js) ohne Vorschau: Enter oder Klick öffnet das
 * Protokoll, angehakte Zeilen bekommen die Leiste mit „Löschen"
 * (POST admin/list/delete mit ids[], CSRF-Token, Rückfrage). Einsätze aus dem
 * Verbund stehen nur lesend dabei, ohne Kästchen.
 *
 * @var array<int,array<string,mixed>> $incidents
 * @var bool                           $showArchived
 * @var \App\Support\ListQuery         $list
 */

use App\Security\CsrfProtection;

$layout = 'admin';
$bodyId = 'protokolle';
$SITE_TITLE = 'Einsatz-QM';

$pgPath  = 'firetab/admin/list';
$pgLabel = 'Protokolle';

$statusMap = [
    0 => ['secondary', 'Ungesehen'],
    1 => ['warn', 'In Prüfung'],
    2 => ['ok', 'Freigegeben'],
    3 => ['danger', 'Ungenügend'],
    4 => ['secondary', 'Ausgeblendet'],
];
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Protokolle</span> <span class="ignis-breadcrumb__item is-active">Einsatz-QM</span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Feuerwehr</p>
                    <h1>Einsatzprotokolle (QM)</h1>
                    <p class="twplus-page-header__description">Alle Einsätze mit Prüfstand, Archiv und Sammelaktionen.</p>
                </div>
                <div class="header-actions twplus-page-header__actions">
                    <button type="button" class="ignis-btn ignis-btn--secondary" onclick="showBulkDeleteModal()">
                        <i class="fa-solid fa-broom"></i> Leere Protokolle löschen
                    </button>
                    <a href="<?= BASE_PATH ?>firetab/create" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-plus"></i> Neu</a>
                </div>
            </div>

            <form class="ignis-list-toolbar" method="get" action="<?= BASE_PATH . $pgPath ?>" role="search">
                <?php if ($showArchived): ?>
                    <input type="hidden" name="show_archived" value="1">
                <?php endif; ?>
                <?php if ($list->sort !== ($showArchived ? 'archived' : 'created') || $list->dir !== 'desc'): ?>
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($list->sort) ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($list->dir) ?>">
                <?php endif; ?>
                <label class="ignis-list-toolbar__search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input class="ignis-input" type="search" name="q" value="<?= htmlspecialchars($list->q) ?>" placeholder="Einsatznummer, Ort, Stichwort oder Leiter" aria-label="Protokolle suchen">
                </label>
                <button type="submit" class="ignis-btn ignis-btn--secondary ignis-btn--sm">Suchen</button>
                <?php if ($list->q !== ''): ?>
                    <a class="ignis-btn ignis-btn--ghost ignis-btn--sm" href="<?= htmlspecialchars($list->url($pgPath, ['q' => null, 'page' => null])) ?>">Zurücksetzen</a>
                <?php endif; ?>
                <span class="ignis-list-toolbar__spacer"></span>
                <nav class="ignis-filter-links" aria-label="Archiv">
                    <a href="<?= htmlspecialchars($list->url($pgPath, ['show_archived' => null, 'sort' => null, 'dir' => null, 'page' => null])) ?>"<?= !$showArchived ? ' class="is-active" aria-current="true"' : '' ?>>Aktiv</a>
                    <a href="<?= htmlspecialchars($list->url($pgPath, ['show_archived' => '1', 'sort' => null, 'dir' => null, 'page' => null])) ?>"<?= $showArchived ? ' class="is-active" aria-current="true"' : '' ?>>Archiv</a>
                </nav>
            </form>

            <?php if ($showArchived): ?>
                <div class="ignis-alert ignis-alert--info mb-3">
                    <i class="fa-solid fa-archive mr-2"></i>
                    Sie sehen archivierte Einsätze. Diese sind aus den normalen Listen ausgeblendet.
                </div>
            <?php endif; ?>

            <div class="ignis-workbench ignis-workbench--plain" data-ignis-workbench>
                <?php if (!$showArchived): ?>
                    <form method="POST" action="<?= BASE_PATH ?>firetab/admin/list/delete" class="ignis-bulkbar" data-ignis-bulkbar hidden>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CsrfProtection::getToken(), ENT_QUOTES) ?>">
                        <span class="ignis-bulkbar__count"><b data-ignis-bulk-count>0</b> ausgewählt</span>
                        <span class="ignis-bulkbar__spacer"></span>
                        <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--danger" data-ignis-bulk-confirm="{n} ausgewählte Protokolle wirklich löschen? Sie werden archiviert und ausgeblendet."><i class="fa-solid fa-trash" aria-hidden="true"></i> Löschen</button>
                        <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost" data-ignis-bulk-clear>Abbrechen</button>
                    </form>
                <?php endif; ?>
                <div class="twplus-table-card">
                    <div class="twplus-table-card__scroll">
                        <table class="ignis-table" id="table-incidents">
                            <thead>
                                <tr>
                                    <?php if (!$showArchived): ?>
                                        <th scope="col" class="ignis-table__check"><input type="checkbox" data-ignis-select-all aria-label="Alle auswählen"></th>
                                    <?php endif; ?>
                                    <?= $list->th('nr', 'Einsatznummer', $pgPath) ?>
                                    <?= $list->th('start', 'Beginn', $pgPath) ?>
                                    <?= $list->th('location', 'Ort', $pgPath) ?>
                                    <?= $list->th('keyword', 'Stichwort', $pgPath) ?>
                                    <?= $list->th('leader', 'Leiter', $pgPath) ?>
                                    <?= $list->th('status', 'Status', $pgPath) ?>
                                    <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($incidents === []): ?>
                                    <tr><td colspan="<?= $showArchived ? 7 : 8 ?>" class="ignis-table-empty">Keine Protokolle gefunden.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($incidents as $i): ?>
                                    <?php
                                    $isFederated = !empty($i['_federation_readonly']);
                                    if ($isFederated) {
                                        $i['finalized'] = $i['finalized'] ?? 1;
                                        $i['status'] = $i['status'] ?? 2;
                                        $i['started_at'] = $i['created_at'] ?? date('Y-m-d H:i:s');
                                        $i['location'] = $i['location'] ?? '';
                                        $i['keyword'] = $i['keyword'] ?? '';
                                    }
                                    if (!$i['finalized']) {
                                        [$statusChip, $statusText] = ['secondary', 'Unfertig'];
                                    } else {
                                        [$statusChip, $statusText] = $statusMap[(int) $i['status']] ?? ['secondary', 'Unbekannt'];
                                    }
                                    $startDt = new DateTime($i['started_at'], new DateTimeZone('UTC'));
                                    $startDt->setTimezone(new DateTimeZone('Europe/Berlin'));
                                    $viewUrl = BASE_PATH . 'firetab/view?id=' . (int) $i['id'];
                                    ?>
                                    <?php if ($isFederated): ?>
                                        <tr class="is-muted">
                                    <?php else: ?>
                                        <tr data-ignis-row="<?= (int) $i['id'] ?>" data-href="<?= htmlspecialchars($viewUrl, ENT_QUOTES) ?>" tabindex="0">
                                    <?php endif; ?>
                                        <?php if (!$showArchived): ?>
                                            <td class="ignis-table__check"><?php if (!$isFederated): ?><input type="checkbox" data-ignis-select value="<?= (int) $i['id'] ?>" aria-label="Protokoll auswählen"><?php endif; ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <?php if ($isFederated): ?>
                                                <?= htmlspecialchars($i['incident_number'] ?? '-') ?> <span class="ignis-chip ignis-chip--secondary"><?= htmlspecialchars($i['_federation_source'] ?? '') ?></span>
                                            <?php else: ?>
                                                <a href="<?= htmlspecialchars($viewUrl, ENT_QUOTES) ?>" class="ignis-mono"><?= htmlspecialchars($i['incident_number'] ?? '-') ?></a>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($startDt->format('d.m.Y H:i')) ?></td>
                                        <td><?= htmlspecialchars($i['location']) ?></td>
                                        <td><?= htmlspecialchars($i['keyword']) ?></td>
                                        <td><?= htmlspecialchars($i['leader_name'] ?? '-') ?></td>
                                        <td><span class="ignis-chip ignis-chip--dot ignis-chip--<?= $statusChip ?>"><?= htmlspecialchars($statusText) ?></span></td>
                                        <td class="ignis-table__actions">
                                            <?php if ($isFederated): ?>
                                                <span class="ignis-list-meta">nur lesen</span>
                                            <?php else: ?>
                                                <div class="ignis-row-actions">
                                                    <a class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" href="<?= htmlspecialchars($viewUrl, ENT_QUOTES) ?>" data-ignis-tooltip="Öffnen" aria-label="Öffnen"><i class="fa-solid fa-arrow-right"></i></a>
                                                    <?php if ($showArchived): ?>
                                                        <form method="post" action="<?= BASE_PATH ?>firetab/actions" class="inline">
                                                            <input type="hidden" name="action" value="unarchive_incident">
                                                            <input type="hidden" name="incident_id" value="<?= (int) $i['id'] ?>">
                                                            <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" data-ignis-tooltip="Wiederherstellen" aria-label="Wiederherstellen"><i class="fa-solid fa-box-open"></i></button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="post" action="<?= BASE_PATH ?>firetab/actions" class="inline">
                                                            <input type="hidden" name="action" value="archive_incident">
                                                            <input type="hidden" name="incident_id" value="<?= (int) $i['id'] ?>">
                                                            <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" data-ignis-tooltip="Archivieren" aria-label="Archivieren" onclick="event.preventDefault(); showConfirm('Einsatz wirklich archivieren? Er wird aus allen Listen ausgeblendet.', {danger: true, confirmText: 'Archivieren', title: 'Einsatz archivieren'}).then(result => { if(result) this.closest('form').submit(); });"><i class="fa-solid fa-archive"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php require dirname(__DIR__, 4) . '/templates/partials/pagination.php'; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- bulkDeleteModal-Markup entfaellt: showBulkDeleteModal() erstellt
         eine Dialog-Instanz mit dynamisch ueberschriebenem Body (Field-Select
         -> Preview -> Result-State). Der "Jetzt loeschen"-Action-Button wird
         IN den Body gerendert, weil er nur im Preview-State sichtbar ist. -->
    <div id="bulkDeleteContentHelper" style="display: none;"></div>

    <script>
        // Dialog-Instanz halten wir in einem Closure, damit alle drei States
        // (Field-Select, Preview, Result) den gleichen Body ueberschreiben.
        let bulkDeleteDialog = null;
        function getBulkDeleteBody() {
            return bulkDeleteDialog ? bulkDeleteDialog.element.querySelector('.ignis-dialog__body > div') : null;
        }
        function setBulkDeleteContent(html) {
            const body = getBulkDeleteBody();
            if (body) body.innerHTML = html;
        }

        window.showBulkDeleteModal = function() {
            // Initial-Body als Container, in den die States nachgeschoben werden.
            const initialContent = document.createElement('div');
            initialContent.innerHTML = `
                <div class="flex justify-center">
                    <div class="ignis-spinner ignis-spinner--lg" role="status"><span class="sr-only">Laden...</span></div>
                </div>`;

            // Wenn Dialog schon offen: nur Body zuruecksetzen, nicht neu instanziieren.
            if (bulkDeleteDialog && bulkDeleteDialog.element) {
                const bodyEl = getBulkDeleteBody();
                if (bodyEl) bodyEl.innerHTML = initialContent.innerHTML;
            } else {
                bulkDeleteDialog = new Dialog({
                    title:   'Leere Einsatzprotokolle löschen',
                    size:    'lg',
                    body:    initialContent,
                    actions: [{ label: 'Abbrechen', variant: 'ghost', close: true }],
                    onClose: function () { bulkDeleteDialog = null; },
                });
                bulkDeleteDialog.open();
            }

            fetch('<?= BASE_PATH ?>api/fire/bulk-delete-empty')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.fields) {
                        let fieldsHtml = '';
                        for (const [key, label] of Object.entries(data.fields)) {
                            const checked = key === 'location' ? 'checked' : '';
                            fieldsHtml += `
                                <div class="ignis-checkbox">
                                    <input class="bulk-field-checkbox" type="checkbox" value="${key}" id="field_${key}" ${checked}>
                                    <label for="field_${key}">${label}</label>
                                </div>`;
                        }
                        setBulkDeleteContent(`
                            <div class="ignis-alert ignis-alert--info">
                                <i class="fa-solid fa-circle-info"></i>
                                <strong>Felder auswählen</strong>
                                <p class="mb-0 mt-2">Wählen Sie die Felder aus, die leer sein müssen, damit ein Protokoll gelöscht wird. Alle ausgewählten Bedingungen müssen zutreffen.</p>
                            </div>
                            <form id="bulkDeleteFieldsForm">
                                <div class="mb-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div>
                                        <label class="ignis-field__label font-bold">Zeitraum:</label>
                                        <select class="ignis-input" id="timePeriod">
                                            <option value="7">Letzte 7 Tage</option>
                                            <option value="30" selected>Letzte 30 Tage</option>
                                            <option value="90">Letzte 90 Tage</option>
                                            <option value="180">Letzte 180 Tage</option>
                                            <option value="all">Insgesamt (alle Protokolle)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="ignis-field__label font-bold">Status:</label>
                                        <select class="ignis-input" id="statusFilter">
                                            <option value="all" selected>Alle</option>
                                            <option value="unfinalized">Nur unfertige</option>
                                            <option value="finalized">Nur abgeschlossene</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="ignis-field__label font-bold">Leere Felder (ALLE müssen leer sein):</label>
                                    ${fieldsHtml}
                                </div>
                                <button type="button" class="ignis-btn ignis-btn--secondary" onclick="previewBulkDelete()">
                                    <i class="fa-solid fa-search"></i> Vorschau anzeigen
                                </button>
                            </form>`);
                    } else {
                        setBulkDeleteContent(`<div class="ignis-alert ignis-alert--error"><i class="fa-solid fa-exclamation-circle"></i> Fehler: ${data.message || 'Unbekannter Fehler'}</div>`);
                    }
                })
                .catch(error => {
                    setBulkDeleteContent(`<div class="ignis-alert ignis-alert--error"><i class="fa-solid fa-exclamation-circle"></i> Fehler: ${error.message}</div>`);
                });
        };

        window.previewBulkDelete = function() {
            const checkboxes = document.querySelectorAll('.bulk-field-checkbox:checked');
            const selectedFields = Array.from(checkboxes).map(cb => cb.value);
            const timePeriod = document.getElementById('timePeriod').value;
            const statusFilter = document.getElementById('statusFilter').value;

            if (selectedFields.length === 0) {
                showToast('Bitte wählen Sie mindestens ein Feld aus.', 'warning');
                return;
            }

            setBulkDeleteContent(`<div class="flex justify-center"><div class="ignis-spinner ignis-spinner--lg" role="status"><span class="sr-only">Lade Vorschau...</span></div></div>`);

            const formData = new FormData();
            selectedFields.forEach(field => formData.append('fields[]', field));
            formData.append('preview', '1');
            formData.append('timePeriod', timePeriod);
            formData.append('statusFilter', statusFilter);

            fetch('<?= BASE_PATH ?>api/fire/bulk-delete-empty', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.count === 0) {
                            setBulkDeleteContent(`
                                <div class="ignis-alert ignis-alert--info"><i class="fa-solid fa-circle-info"></i> <strong>Keine passenden Protokolle gefunden</strong><p class="mb-0 mt-2">Es wurden keine Protokolle gefunden, die alle ausgewählten Kriterien erfüllen.</p></div>
                                <button type="button" class="ignis-btn ignis-btn--ghost" onclick="showBulkDeleteModal()"><i class="fa-solid fa-arrow-left"></i> Zurück</button>`);
                        } else {
                            let protocolsList = data.protocols.map(p => {
                                const date = new Date(p.created_at);
                                const dateStr = date.toLocaleDateString('de-DE') + ' ' + date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
                                const statusBadge = p.finalized == 1 ? '<span class="ignis-chip ignis-chip--ok">Abgeschlossen</span>' : '<span class="ignis-chip ignis-chip--secondary">Unfertig</span>';
                                return `<tr><td>${p.incident_number || '<em>-</em>'}</td><td>${p.location || '<em>-</em>'}</td><td>${p.keyword || '<em>-</em>'}</td><td>${p.leader_name || '<em>-</em>'}</td><td>${dateStr}</td><td>${statusBadge}</td></tr>`;
                            }).join('');

                            // "Jetzt loeschen"-Button wandert in den Body, weil
                            // ignis-Dialog kein dynamisches Action-Hinzufuegen
                            // unterstuetzt — er ist nur im Preview-State sichtbar.
                            setBulkDeleteContent(`
                                <div class="ignis-alert ignis-alert--warn"><i class="fa-solid fa-exclamation-triangle"></i> <strong>Achtung!</strong><p class="mb-0 mt-2">Es wurden <strong>${data.count} Protokoll(e)</strong> gefunden, die archiviert werden.</p><p class="mb-0 mt-2"><small>Leere Felder: ${data.selectedFieldsLabel}</small></p></div>
                                <div class="overflow-x-auto" style="max-height: 400px; overflow-y: auto;">
                                    <table class="ignis-table"><thead><tr><th>Einsatznummer</th><th>Ort</th><th>Stichwort</th><th>Leiter</th><th>Angelegt am</th><th>Status</th></tr></thead><tbody>${protocolsList}</tbody></table>
                                </div>
                                <div class="text-right mt-3">
                                    <button type="button" class="ignis-btn ignis-btn--danger" onclick="executeBulkDelete(this)">
                                        <i class="fa-solid fa-trash"></i> Jetzt löschen
                                    </button>
                                </div>`);
                            window.bulkDeleteSelectedFields = selectedFields;
                            window.bulkDeleteTimePeriod = timePeriod;
                            window.bulkDeleteStatusFilter = statusFilter;
                        }
                    } else {
                        setBulkDeleteContent(`<div class="ignis-alert ignis-alert--error"><i class="fa-solid fa-exclamation-circle"></i> Fehler: ${data.message || 'Unbekannter Fehler'}</div><button type="button" class="ignis-btn ignis-btn--ghost" onclick="showBulkDeleteModal()"><i class="fa-solid fa-arrow-left"></i> Zurück</button>`);
                    }
                })
                .catch(error => {
                    setBulkDeleteContent(`<div class="ignis-alert ignis-alert--error"><i class="fa-solid fa-exclamation-circle"></i> Fehler: ${error.message}</div><button type="button" class="ignis-btn ignis-btn--ghost" onclick="showBulkDeleteModal()"><i class="fa-solid fa-arrow-left"></i> Zurück</button>`);
                });
        };

        // executeBulkDelete bekommt den Click-Button als Param uebergeben
        // statt event.target zu nutzen (das funktioniert nur in inline-onclick).
        window.executeBulkDelete = function(deleteButton) {
            const originalText = deleteButton.innerHTML;

            if (!window.bulkDeleteSelectedFields || window.bulkDeleteSelectedFields.length === 0) {
                showToast('Keine Felder ausgewählt', 'warning');
                return;
            }

            deleteButton.innerHTML = '<span class="ignis-spinner ignis-spinner--sm" role="status" aria-hidden="true"></span> Lösche...';
            deleteButton.disabled = true;

            const formData = new FormData();
            window.bulkDeleteSelectedFields.forEach(field => formData.append('fields[]', field));
            formData.append('timePeriod', window.bulkDeleteTimePeriod || '30');
            formData.append('statusFilter', window.bulkDeleteStatusFilter || 'all');

            fetch('<?= BASE_PATH ?>api/fire/bulk-delete-empty', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        setBulkDeleteContent(`<div class="ignis-alert ignis-alert--ok"><i class="fa-solid fa-check-circle"></i> <strong>Erfolgreich!</strong><p class="mb-0 mt-2">${data.deleted} Protokoll(e) wurden erfolgreich archiviert.</p></div>`);
                        setTimeout(() => { location.reload(); }, 2000);
                    } else {
                        setBulkDeleteContent(`<div class="ignis-alert ignis-alert--error"><i class="fa-solid fa-exclamation-circle"></i> Fehler beim Löschen: ${data.message || 'Unbekannter Fehler'}</div>`);
                        deleteButton.innerHTML = originalText;
                        deleteButton.disabled = false;
                    }
                })
                .catch(error => {
                    setBulkDeleteContent(`<div class="ignis-alert ignis-alert--error"><i class="fa-solid fa-exclamation-circle"></i> Fehler beim Löschen: ${error.message}</div>`);
                    deleteButton.innerHTML = originalText;
                    deleteButton.disabled = false;
                });
        };
    </script>
