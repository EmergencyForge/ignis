<?php
/**
 * View: QM-Übersicht aller Einsatzprotokolle (Admin)
 *
 * @var array<int,array<string,mixed>> $incidents
 * @var bool                           $showArchived
 * @var \PDO                           $pdo
 */

use App\Helpers\Flash;
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../../assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" data-page="protokolle">
    <?php include __DIR__ . '/../../assets/components/navbar.php'; ?>
    <div class="container my-4">
        <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Protokolle</span> <span class="ignis-breadcrumb__item is-active">Einsatz QM</span></nav>
        <div class="page-header mb-4">
            <h1>Einsatzprotokolle (QM)</h1>
            <div class="header-actions">
                <div class="flex items-center gap-3">
                    <div class="btn-toolbar-group">
                        <a href="<?= BASE_PATH ?>einsatz/admin/list" class="ignis-btn <?= !$showArchived ? 'active' : '' ?>">Aktiv</a>
                        <a href="<?= BASE_PATH ?>einsatz/admin/list?show_archived=1" class="ignis-btn <?= $showArchived ? 'active' : '' ?>">Archiv</a>
                    </div>
                    <a href="<?= BASE_PATH ?>einsatz/create" class="ignis-btn ignis-btn--success"><i class="fa-solid fa-plus"></i> Neu</a>
                    <button onclick="showBulkDeleteModal()" class="ignis-btn ignis-btn--outline-danger ignis-btn--sm">
                        <i class="fa-solid fa-trash-can"></i> Protokolle löschen
                    </button>
                </div>
            </div>
        </div>
        <?php Flash::render(); ?>

        <?php if ($showArchived): ?>
            <div class="ignis-alert ignis-alert--info mb-3">
                <i class="fa-solid fa-archive mr-2"></i>
                Sie sehen archivierte Einsätze. Diese sind aus den normalen Listen ausgeblendet.
            </div>
        <?php endif; ?>

        <div class="intra__tile p-3">
            <table class="table table-striped" id="table-incidents">
                <thead>
                    <tr>
                        <th>Einsatznummer</th>
                        <th>Beginn</th>
                        <th>Ort</th>
                        <th>Stichwort</th>
                        <th>Leiter</th>
                        <th>Status</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
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
                            $statusClass = 'status-muted';
                            $statusText = 'Unfertig';
                        } else {
                            $statusMap = [
                                0 => ['status-muted', 'Ungesehen'],
                                1 => ['status-warning', 'In Prüfung'],
                                2 => ['status-success', 'Freigegeben'],
                                3 => ['status-danger', 'Ungenügend'],
                                4 => ['status-muted', 'Ausgeblendet'],
                            ];
                            $s = (int)$i['status'];
                            [$statusClass, $statusText] = $statusMap[$s] ?? ['status-muted', 'Unbekannt'];
                        }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($i['incident_number'] ?? '-') ?><?php if ($isFederated): ?> <span class="badge" style="background:rgba(255,255,255,0.1);font-size:0.6rem;"><?= htmlspecialchars($i['_federation_source'] ?? '') ?></span><?php endif; ?></td>
                            <td><?php
                                $startDt = new DateTime($i['started_at'], new DateTimeZone('UTC'));
                                $startDt->setTimezone(new DateTimeZone('Europe/Berlin'));
                                echo htmlspecialchars($startDt->format('d.m.Y H:i'));
                            ?></td>
                            <td><?= htmlspecialchars($i['location']) ?></td>
                            <td><?= htmlspecialchars($i['keyword']) ?></td>
                            <td><?= htmlspecialchars($i['leader_name'] ?? '-') ?></td>
                            <td><span class="badge-status <?= $statusClass ?>"><span class="status-dot"></span><?= htmlspecialchars($statusText) ?></span></td>
                            <td>
                                <?php if ($isFederated): ?>
                                    <span style="font-size:var(--fs-xs);color:var(--text-dimmed);">read-only</span>
                                <?php else: ?>
                                <a class="ignis-btn ignis-btn--sm ignis-btn--soft-primary" href="<?= BASE_PATH ?>einsatz/view?id=<?= (int)$i['id'] ?>">Öffnen</a>
                                <?php if ($showArchived): ?>
                                    <form method="post" action="<?= BASE_PATH ?>einsatz/actions" class="inline">
                                        <input type="hidden" name="action" value="unarchive_incident">
                                        <input type="hidden" name="incident_id" value="<?= (int)$i['id'] ?>">
                                        <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--success ignis-btn--icon" title="Wiederherstellen">
                                            <i class="fa-solid fa-box-open"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?= BASE_PATH ?>einsatz/actions" class="inline">
                                        <input type="hidden" name="action" value="archive_incident">
                                        <input type="hidden" name="incident_id" value="<?= (int)$i['id'] ?>">
                                        <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--soft-warning ignis-btn--icon" title="Archivieren" onclick="event.preventDefault(); showConfirm('Einsatz wirklich archivieren? Er wird aus allen Listen ausgeblendet.', {danger: true, confirmText: 'Archivieren', title: 'Einsatz archivieren'}).then(result => { if(result) this.closest('form').submit(); });">
                                            <i class="fa-solid fa-archive"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bulk Delete Modal -->
    <div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkDeleteModalLabel">Einsatzprotokolle löschen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="bulkDeleteContent">
                    <div class="flex justify-center">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Laden...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" id="bulkDeleteFooter" style="display: none;">
                    <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="button" class="ignis-btn ignis-btn--ghost-danger" onclick="executeBulkDelete()">
                        <i class="fa-solid fa-trash"></i> Jetzt löschen
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#table-incidents').DataTable({
                stateSave: true,
                order: [[0, 'desc']],
                pageLength: 20,
                language: window.IgnisDataTableLang('Einträge')
            });
        });

        window.showBulkDeleteModal = function() {
            const modal = new bootstrap.Modal(document.getElementById('bulkDeleteModal'));
            document.getElementById('bulkDeleteContent').innerHTML = `
                <div class="flex justify-center">
                    <div class="spinner-border" role="status"><span class="sr-only">Laden...</span></div>
                </div>`;
            document.getElementById('bulkDeleteFooter').style.display = 'none';
            modal.show();

            fetch('<?= BASE_PATH ?>api/fire/bulk-delete-empty')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.fields) {
                        let fieldsHtml = '';
                        for (const [key, label] of Object.entries(data.fields)) {
                            const checked = key === 'location' ? 'checked' : '';
                            fieldsHtml += `
                                <div class="form-check">
                                    <input class="form-check-input bulk-field-checkbox" type="checkbox" value="${key}" id="field_${key}" ${checked}>
                                    <label class="form-check-label" for="field_${key}">${label}</label>
                                </div>`;
                        }
                        document.getElementById('bulkDeleteContent').innerHTML = `
                            <div class="ignis-alert ignis-alert--info">
                                <i class="fa-solid fa-circle-info"></i>
                                <strong>Felder auswählen</strong>
                                <p class="mb-0 mt-2">Wählen Sie die Felder aus, die leer sein müssen, damit ein Protokoll gelöscht wird. Alle ausgewählten Bedingungen müssen zutreffen.</p>
                            </div>
                            <form id="bulkDeleteFieldsForm">
                                <div class="mb-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div>
                                        <label class="ignis-field__label font-bold">Zeitraum:</label>
                                        <select class="form-select" id="timePeriod">
                                            <option value="7">Letzte 7 Tage</option>
                                            <option value="30" selected>Letzte 30 Tage</option>
                                            <option value="90">Letzte 90 Tage</option>
                                            <option value="180">Letzte 180 Tage</option>
                                            <option value="all">Insgesamt (alle Protokolle)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="ignis-field__label font-bold">Status:</label>
                                        <select class="form-select" id="statusFilter">
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
                                <button type="button" class="ignis-btn ignis-btn--soft-primary" onclick="previewBulkDelete()">
                                    <i class="fa-solid fa-search"></i> Vorschau anzeigen
                                </button>
                            </form>`;
                    } else {
                        document.getElementById('bulkDeleteContent').innerHTML = `<div class="ignis-alert ignis-alert--danger"><i class="fa-solid fa-exclamation-circle"></i> Fehler: ${data.message || 'Unbekannter Fehler'}</div>`;
                    }
                })
                .catch(error => {
                    document.getElementById('bulkDeleteContent').innerHTML = `<div class="ignis-alert ignis-alert--danger"><i class="fa-solid fa-exclamation-circle"></i> Fehler: ${error.message}</div>`;
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

            document.getElementById('bulkDeleteContent').innerHTML = `<div class="flex justify-center"><div class="spinner-border" role="status"><span class="sr-only">Lade Vorschau...</span></div></div>`;

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
                            document.getElementById('bulkDeleteContent').innerHTML = `
                                <div class="ignis-alert ignis-alert--info"><i class="fa-solid fa-circle-info"></i> <strong>Keine passenden Protokolle gefunden</strong><p class="mb-0 mt-2">Es wurden keine Protokolle gefunden, die alle ausgewählten Kriterien erfüllen.</p></div>
                                <button type="button" class="ignis-btn ignis-btn--ghost" onclick="showBulkDeleteModal()"><i class="fa-solid fa-arrow-left"></i> Zurück</button>`;
                        } else {
                            let protocolsList = data.protocols.map(p => {
                                const date = new Date(p.created_at);
                                const dateStr = date.toLocaleDateString('de-DE') + ' ' + date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
                                const statusBadge = p.finalized == 1 ? '<span class="ignis-chip ignis-chip--success">Abgeschlossen</span>' : '<span class="ignis-chip">Unfertig</span>';
                                return `<tr><td>${p.incident_number || '<em>-</em>'}</td><td>${p.location || '<em>-</em>'}</td><td>${p.keyword || '<em>-</em>'}</td><td>${p.leader_name || '<em>-</em>'}</td><td>${dateStr}</td><td>${statusBadge}</td></tr>`;
                            }).join('');

                            document.getElementById('bulkDeleteContent').innerHTML = `
                                <div class="ignis-alert ignis-alert--warning"><i class="fa-solid fa-exclamation-triangle"></i> <strong>Achtung!</strong><p class="mb-0 mt-2">Es wurden <strong>${data.count} Protokoll(e)</strong> gefunden, die archiviert werden.</p><p class="mb-0 mt-2"><small>Leere Felder: ${data.selectedFieldsLabel}</small></p></div>
                                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                    <table class="table table-sm table-striped"><thead class="sticky-top bg-[rgba(0,0,0,0.3)]"><tr><th>Einsatznummer</th><th>Ort</th><th>Stichwort</th><th>Leiter</th><th>Angelegt am</th><th>Status</th></tr></thead><tbody>${protocolsList}</tbody></table>
                                </div>`;
                            document.getElementById('bulkDeleteFooter').style.display = 'flex';
                            window.bulkDeleteSelectedFields = selectedFields;
                            window.bulkDeleteTimePeriod = timePeriod;
                            window.bulkDeleteStatusFilter = statusFilter;
                        }
                    } else {
                        document.getElementById('bulkDeleteContent').innerHTML = `<div class="ignis-alert ignis-alert--danger"><i class="fa-solid fa-exclamation-circle"></i> Fehler: ${data.message || 'Unbekannter Fehler'}</div><button type="button" class="ignis-btn ignis-btn--ghost" onclick="showBulkDeleteModal()"><i class="fa-solid fa-arrow-left"></i> Zurück</button>`;
                    }
                })
                .catch(error => {
                    document.getElementById('bulkDeleteContent').innerHTML = `<div class="ignis-alert ignis-alert--danger"><i class="fa-solid fa-exclamation-circle"></i> Fehler: ${error.message}</div><button type="button" class="ignis-btn ignis-btn--ghost" onclick="showBulkDeleteModal()"><i class="fa-solid fa-arrow-left"></i> Zurück</button>`;
                });
        };

        window.executeBulkDelete = function() {
            const deleteButton = event.target.closest('button');
            const originalText = deleteButton.innerHTML;

            if (!window.bulkDeleteSelectedFields || window.bulkDeleteSelectedFields.length === 0) {
                showToast('Keine Felder ausgewählt', 'warning');
                return;
            }

            deleteButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Lösche...';
            deleteButton.disabled = true;

            const formData = new FormData();
            window.bulkDeleteSelectedFields.forEach(field => formData.append('fields[]', field));
            formData.append('timePeriod', window.bulkDeleteTimePeriod || '30');
            formData.append('statusFilter', window.bulkDeleteStatusFilter || 'all');

            fetch('<?= BASE_PATH ?>api/fire/bulk-delete-empty', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('bulkDeleteContent').innerHTML = `<div class="ignis-alert ignis-alert--success"><i class="fa-solid fa-check-circle"></i> <strong>Erfolgreich!</strong><p class="mb-0 mt-2">${data.deleted} Protokoll(e) wurden erfolgreich archiviert.</p></div>`;
                        document.getElementById('bulkDeleteFooter').style.display = 'none';
                        setTimeout(() => { location.reload(); }, 2000);
                    } else {
                        document.getElementById('bulkDeleteContent').innerHTML = `<div class="ignis-alert ignis-alert--danger"><i class="fa-solid fa-exclamation-circle"></i> Fehler beim Löschen: ${data.message || 'Unbekannter Fehler'}</div>`;
                        deleteButton.innerHTML = originalText;
                        deleteButton.disabled = false;
                    }
                })
                .catch(error => {
                    document.getElementById('bulkDeleteContent').innerHTML = `<div class="ignis-alert ignis-alert--danger"><i class="fa-solid fa-exclamation-circle"></i> Fehler beim Löschen: ${error.message}</div>`;
                    deleteButton.innerHTML = originalText;
                    deleteButton.disabled = false;
                });
        };
    </script>
    <?php include __DIR__ . '/../../assets/components/footer.php'; ?>
</body>

</html>
