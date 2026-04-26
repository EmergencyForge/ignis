<?php
/**
 * View: eNOTF Admin/QM Protokollübersicht
 *
 * @var \PDO $pdo
 */

use App\Auth\Permissions;
use App\Helpers\EnotfUrl;
use App\Helpers\Flash;
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    include __DIR__ . "/../../../assets/components/_base/admin/head.php";
    ?>
</head>

<body data-bs-theme="dark" data-page="edivi">
    <?php include __DIR__ . "/../../../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="container my-4">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index.php">Dashboard</a></span> <span class="ignis-breadcrumb__item">Protokolle</span> <span class="ignis-breadcrumb__item is-active">eNOTF QM</span></nav>
            <div class="page-header mb-4">
                <h1>Protokollübersicht</h1>
                <div class="header-actions">
                    <div class="flex items-center gap-3">
                        <div class="btn-toolbar-group">
                            <a href="?view=0" class="btn <?= (!isset($_GET['view']) || $_GET['view'] != 1) ? 'active' : '' ?>">Alle</a>
                            <a href="?view=1" class="btn <?= (isset($_GET['view']) && $_GET['view'] == 1) ? 'active' : '' ?>">Unbearbeitet</a>
                        </div>
                        <?php if (Permissions::check(['admin', 'edivi.edit'])) { ?>
                            <button onclick="showBulkDeleteModal()" class="ignis-btn ignis-btn--outline-danger ignis-btn--sm">
                                <i class="fa-solid fa-trash-can"></i> Leere Protokolle löschen
                            </button>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <?php Flash::render(); ?>
            <div class="row">
                <div class="col mb-5">
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-protokoll">
                            <thead>
                                <th scope="col">Einsatznummer</th>
                                <th scope="col">Patient</th>
                                <th scope="col">Angelegt am</th>
                                <th scope="col">Protokollant</th>
                                <th scope="col">Status</th>
                                <th scope="col"></th>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $pdo->prepare("SELECT * FROM intra_edivi WHERE hidden <> 1");
                                $stmt->execute();
                                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                // Append federated eNOTF protocols (read-only)
                                if (\App\Federation\FederationMiddleware::isEnabled()) {
                                    try {
                                        $fedStmt = $pdo->query("
                                            SELECT fce.cached_data, fl.instance_name
                                            FROM intra_federation_cache_enotf fce
                                            JOIN intra_federation_links fl ON fl.instance_id = fce.source_instance_id AND fl.is_active = 1
                                            ORDER BY fce.protocol_date DESC
                                        ");
                                        foreach ($fedStmt->fetchAll(PDO::FETCH_ASSOC) as $fedRow) {
                                            $p = json_decode($fedRow['cached_data'], true);
                                            if (!$p) continue;
                                            $p['_federation_source'] = $fedRow['instance_name'];
                                            $p['_federation_readonly'] = true;
                                            // Ensure expected keys exist
                                            $p['protokoll_status'] = $p['protokoll_status'] ?? 2;
                                            $p['freigegeben'] = $p['freigegeben'] ?? 1;
                                            $p['hidden_user'] = $p['hidden_user'] ?? 0;
                                            $p['bearbeiter'] = $p['bearbeiter'] ?? '';
                                            $p['freigeber_name'] = $p['freigeber_name'] ?? '';
                                            $p['id'] = 'fed_' . ($p['id'] ?? 0);
                                            $result[] = $p;
                                        }
                                    } catch (\PDOException $e) {
                                        // Silently skip
                                    }
                                }

                                foreach ($result as $row) {
                                    $datetime = new DateTime($row['sendezeit']);
                                    $date = $datetime->format('d.m.Y | H:i');
                                    switch ($row['protokoll_status']) {
                                        case 0:
                                            $status = "<span class='badge text-bg-secondary'>Ungesehen</span>";
                                            break;
                                        case 1:
                                            $status = "<span title='Prüfer: " . $row['bearbeiter'] . "' class='badge text-bg-warning'>in Prüfung</span>";
                                            break;
                                        case 2:
                                            $status = "<span title='Prüfer: " . $row['bearbeiter'] . "' class='badge text-bg-success'>Geprüft</span>";
                                            break;
                                        case 4:
                                            $status = "<span title='Prüfer: " . $row['bearbeiter'] . "' class='badge text-bg-dark'>Ausgeblendet</span>";
                                            break;
                                        default:
                                            $status = "<span title='Prüfer: " . $row['bearbeiter'] . "' class='badge text-bg-danger'>Ungenügend</span>";
                                            break;
                                    }

                                    switch ($row['freigegeben']) {
                                        default:
                                            $freigabe_status = "";
                                            break;
                                        case 1:
                                            if ($row['hidden_user'] != 1) {
                                                $freigabe_status = "<span title='Freigeber: " . htmlspecialchars($row['freigeber_name']) . "' class='badge text-bg-success'>F</span>";
                                            } else {
                                                $freigabe_status = "";
                                            }
                                            break;
                                    }

                                    switch ($row['hidden_user']) {
                                        default:
                                            $hu_status = "";
                                            break;
                                        case 1:
                                            $hu_status = "<span title='Gelöscht: " . $row['freigeber_name'] . "' class='badge text-bg-danger'>G</span>";
                                            break;
                                    }

                                    if (isset($_GET['view']) && $_GET['view'] == 1) {
                                        if ($row['protokoll_status'] != 0 && $row['protokoll_status'] != 1) {
                                            continue;
                                        }
                                    }

                                    $patname = $row['patname'] ?? "Unbekannt";

                                    $isFederated = !empty($row['_federation_readonly']);
                                    $fedBadge = $isFederated ? " <span class='badge' style='background:rgba(255,255,255,0.1);font-size:0.6rem;'>" . htmlspecialchars($row['_federation_source'] ?? '') . "</span>" : "";

                                    $actions = '';
                                    if ($isFederated) {
                                        $actions = "<span style='font-size:var(--fs-xs);color:var(--text-dimmed);'>read-only</span>";
                                    } elseif (Permissions::check(['admin', 'edivi.edit'])) {
                                        $actions = "<button title='QM-Aktionen öffnen' onclick='openQMActions({$row['id']}, \"{$row['enr']}\", \"" . htmlspecialchars($row['patname'] ?? 'Unbekannt') . "\")' class='btn btn-sm btn-soft-primary'><i class='fa-solid fa-exclamation'></i></button> <button title='QM-Log öffnen' onclick='openQMLog({$row['id']}, \"{$row['enr']}\", \"" . htmlspecialchars($row['patname'] ?? 'Unbekannt') . "\")' class='btn btn-sm btn-outline-secondary'><i class='fa-solid fa-clock-rotate-left'></i></button> <a title='Protokoll löschen' href='" . EnotfUrl::admin('delete', ['id' => $row['id']]) . "' class='btn btn-sm btn-outline-danger btn-icon'><i class='fa-solid fa-trash'></i></a>";
                                    }
                                    echo "<tr" . ($isFederated ? " style='opacity:0.85;'" : "") . ">";
                                    echo "<td>" . htmlspecialchars($row['enr'] ?? '') . $fedBadge . "</td>";
                                    echo "<td>" . $patname . "</td>";
                                    echo "<td><span style='display:none'>" . ($row['sendezeit'] ?? '') . "</span>" . $date . "</td>";
                                    echo "<td>" . htmlspecialchars($row['pfname'] ?? '') . " " . $freigabe_status . $hu_status . "</td>";
                                    echo "<td>" . $status . "</td>";
                                    if ($isFederated) {
                                        echo "<td>{$actions}</td>";
                                    } else {
                                        echo "<td><a title='Protokoll ansehen' href='" . EnotfUrl::protokoll($row['enr']) . "' class='btn btn-sm btn-soft-primary' target='_blank'><i class='fa-solid fa-eye'></i></a> {$actions}</td>";
                                    }
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QM Actions Modal -->
    <div class="modal fade" id="qmActionsModal" tabindex="-1" aria-labelledby="qmActionsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qmActionsModalLabel">QM-Funktionen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="qmActionsContent">
                    <div class="flex justify-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Laden...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QM Log Modal -->
    <div class="modal fade" id="qmLogModal" tabindex="-1" aria-labelledby="qmLogModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qmLogModalLabel">QM-Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="qmLogContent">
                    <div class="flex justify-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Laden...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Delete Empty Protocols Modal -->
    <div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkDeleteModalLabel">Leere Protokolle löschen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="bulkDeleteContent">
                    <div class="flex justify-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Laden...</span>
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
            var table = $('#table-protokoll').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [10, 20, 50, 100],
                pageLength: 20,
                order: [
                    [2, 'desc']
                ],
                columnDefs: [{
                    orderable: false,
                    targets: -1
                }],
                language: {
                    "decimal": "",
                    "emptyTable": "Keine Daten vorhanden",
                    "info": "Zeige _START_ bis _END_  | Gesamt: _TOTAL_",
                    "infoEmpty": "Keine Daten verfügbar",
                    "infoFiltered": "| Gefiltert von _MAX_ Protokollen",
                    "infoPostFix": "",
                    "thousands": ",",
                    "lengthMenu": "_MENU_ Protokolle pro Seite anzeigen",
                    "loadingRecords": "Lade...",
                    "processing": "Verarbeite...",
                    "search": "Protokoll suchen:",
                    "zeroRecords": "Keine Einträge gefunden",
                    "paginate": {
                        "first": "Erste",
                        "last": "Letzte",
                        "next": "Nächste",
                        "previous": "Vorherige"
                    },
                    "aria": {
                        "sortAscending": ": aktivieren, um Spalte aufsteigend zu sortieren",
                        "sortDescending": ": aktivieren, um Spalte absteigend zu sortieren"
                    }
                }
            });

            // QM Actions Modal Functions
            window.openQMActions = function(id, enr, patname) {
                const modal = new bootstrap.Modal(document.getElementById('qmActionsModal'));
                document.getElementById('qmActionsModalLabel').textContent = `QM-Funktionen [#${enr}] ${patname}`;

                // Reset content
                document.getElementById('qmActionsContent').innerHTML = `
                    <div class="flex justify-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Laden...</span>
                        </div>
                    </div>
                `;

                modal.show();

                // Load content via AJAX
                fetch(`<?= BASE_PATH ?>enotf/admin/qm-actions-modal.php?id=${id}`)
                    .then(response => response.text())
                    .then(data => {
                        document.getElementById('qmActionsContent').innerHTML = data;
                    })
                    .catch(error => {
                        document.getElementById('qmActionsContent').innerHTML = `
                            <div class="ignis-alert ignis-alert--danger">
                                Fehler beim Laden der QM-Aktionen: ${error.message}
                            </div>
                        `;
                    });
            };

            // QM Log Modal Functions
            window.openQMLog = function(id, enr, patname) {
                const modal = new bootstrap.Modal(document.getElementById('qmLogModal'));
                document.getElementById('qmLogModalLabel').textContent = `QM-Log [#${enr}] ${patname}`;

                // Reset content
                document.getElementById('qmLogContent').innerHTML = `
                    <div class="flex justify-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Laden...</span>
                        </div>
                    </div>
                `;

                modal.show();

                // Load content via AJAX
                fetch(`<?= BASE_PATH ?>enotf/admin/qm-log-modal.php?id=${id}`)
                    .then(response => response.text())
                    .then(data => {
                        document.getElementById('qmLogContent').innerHTML = data;
                    })
                    .catch(error => {
                        document.getElementById('qmLogContent').innerHTML = `
                            <div class="ignis-alert ignis-alert--danger">
                                Fehler beim Laden des QM-Logs: ${error.message}
                            </div>
                        `;
                    });
            };

            // Handle QM Actions form submission
            $(document).on('submit', '#qmActionsForm', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                const submitBtn = this.querySelector('input[type="submit"]');
                const originalText = submitBtn.value;

                submitBtn.value = 'Speichere...';
                submitBtn.disabled = true;

                fetch(this.action, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            bootstrap.Modal.getInstance(document.getElementById('qmActionsModal')).hide();
                            // Reload the page to reflect changes
                            location.reload();
                        } else {
                            showAlert('Fehler beim Speichern: ' + (data.message || 'Unbekannter Fehler'), {
                                type: 'error',
                                title: 'Fehler'
                            });
                        }
                    })
                    .catch(error => {
                        showAlert('Fehler beim Speichern: ' + error.message, {
                            type: 'error',
                            title: 'Fehler'
                        });
                    })
                    .finally(() => {
                        submitBtn.value = originalText;
                        submitBtn.disabled = false;
                    });
            });
        });

        // Bulk Delete Functions
        window.showBulkDeleteModal = function() {
            const modal = new bootstrap.Modal(document.getElementById('bulkDeleteModal'));

            // Reset content and hide footer
            document.getElementById('bulkDeleteContent').innerHTML = `
                <div class="flex justify-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Laden...</span>
                    </div>
                </div>
            `;
            document.getElementById('bulkDeleteFooter').style.display = 'none';

            modal.show();

            // Load preview via AJAX
            fetch('<?= BASE_PATH ?>api/enotf/bulk-delete-empty.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.fields) {
                        let fieldsHtml = '';
                        for (const [key, label] of Object.entries(data.fields)) {
                            const checked = key === 'patname' ? 'checked' : '';
                            fieldsHtml += `
                                <div class="form-check">
                                    <input class="form-check-input bulk-field-checkbox" type="checkbox" value="${key}" id="field_${key}" ${checked}>
                                    <label class="form-check-label" for="field_${key}">
                                        ${label}
                                    </label>
                                </div>
                            `;
                        }

                        document.getElementById('bulkDeleteContent').innerHTML = `
                            <div class="ignis-alert ignis-alert--info">
                                <i class="fa-solid fa-circle-info"></i> 
                                <strong>Felder auswählen</strong>
                                <p class="mb-0 mt-2">Wählen Sie die Felder aus, die leer sein müssen, damit ein Protokoll gelöscht wird.</p>
                            </div>
                            <form id="bulkDeleteFieldsForm">
                                <div class="mb-3">
                                    <label class="ignis-field__label font-bold">Zeitraum:</label>
                                    <select class="form-select" id="timePeriod">
                                        <option value="7">Letzte 7 Tage</option>
                                        <option value="30" selected>Letzte 30 Tage</option>
                                        <option value="90">Letzte 90 Tage</option>
                                        <option value="180">Letzte 180 Tage</option>
                                        <option value="all">Insgesamt (alle Protokolle)</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="ignis-field__label font-bold">Leere Felder (ALLE müssen leer sein):</label>
                                    ${fieldsHtml}
                                </div>
                                <button type="button" class="ignis-btn ignis-btn--soft-primary" onclick="previewBulkDelete()">
                                    <i class="fa-solid fa-search"></i> Vorschau anzeigen
                                </button>
                            </form>
                        `;
                    } else {
                        document.getElementById('bulkDeleteContent').innerHTML = `
                            <div class="ignis-alert ignis-alert--danger">
                                <i class="fa-solid fa-exclamation-circle"></i> 
                                Fehler: ${data.message || 'Unbekannter Fehler'}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    document.getElementById('bulkDeleteContent').innerHTML = `
                        <div class="ignis-alert ignis-alert--danger">
                            <i class="fa-solid fa-exclamation-circle"></i> 
                            Fehler: ${error.message}
                        </div>
                    `;
                });
        };

        window.previewBulkDelete = function() {
            const checkboxes = document.querySelectorAll('.bulk-field-checkbox:checked');
            const selectedFields = Array.from(checkboxes).map(cb => cb.value);
            const timePeriod = document.getElementById('timePeriod').value;

            if (selectedFields.length === 0) {
                showToast('Bitte wählen Sie mindestens ein Feld aus.', 'warning');
                return;
            }

            document.getElementById('bulkDeleteContent').innerHTML = `
                <div class="flex justify-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Lade Vorschau...</span>
                    </div>
                </div>
            `;

            const formData = new FormData();
            selectedFields.forEach(field => formData.append('fields[]', field));
            formData.append('preview', '1');
            formData.append('timePeriod', timePeriod);

            fetch('<?= BASE_PATH ?>api/enotf/bulk-delete-empty.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.count === 0) {
                            document.getElementById('bulkDeleteContent').innerHTML = `
                                <div class="ignis-alert ignis-alert--info">
                                    <i class="fa-solid fa-circle-info"></i> 
                                    <strong>Keine leeren Protokolle gefunden</strong>
                                    <p class="mb-0 mt-2">Es wurden keine Protokolle gefunden, die alle ausgewählten Kriterien erfüllen.</p>
                                </div>
                                <button type="button" class="ignis-btn ignis-btn--ghost" onclick="showBulkDeleteModal()">
                                    <i class="fa-solid fa-arrow-left"></i> Zurück
                                </button>
                            `;
                        } else {
                            let protocolsList = data.protocols.map(p => {
                                const date = new Date(p.sendezeit);
                                const dateStr = date.toLocaleDateString('de-DE') + ' ' + date.toLocaleTimeString('de-DE', {
                                    hour: '2-digit',
                                    minute: '2-digit'
                                });
                                return `
                                    <tr>
                                        <td>${p.enr}</td>
                                        <td>${p.patname || '<em>Unbekannt</em>'}</td>
                                        <td>${dateStr}</td>
                                        <td>${p.pfname || ''}</td>
                                    </tr>
                                `;
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
                                        <thead class="sticky-top bg-dark">
                                            <tr>
                                                <th>Einsatznummer</th>
                                                <th>Patient</th>
                                                <th>Angelegt am</th>
                                                <th>Protokollant</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${protocolsList}
                                        </tbody>
                                    </table>
                                </div>
                            `;
                            document.getElementById('bulkDeleteFooter').style.display = 'flex';

                            // Store selected fields and time period for deletion
                            window.bulkDeleteSelectedFields = selectedFields;
                            window.bulkDeleteTimePeriod = timePeriod;
                        }
                    } else {
                        document.getElementById('bulkDeleteContent').innerHTML = `
                            <div class="ignis-alert ignis-alert--danger">
                                <i class="fa-solid fa-exclamation-circle"></i> 
                                Fehler: ${data.message || 'Unbekannter Fehler'}
                            </div>
                            <button type="button" class="ignis-btn ignis-btn--ghost" onclick="showBulkDeleteModal()">
                                <i class="fa-solid fa-arrow-left"></i> Zurück
                            </button>
                        `;
                    }
                })
                .catch(error => {
                    document.getElementById('bulkDeleteContent').innerHTML = `
                        <div class="ignis-alert ignis-alert--danger">
                            <i class="fa-solid fa-exclamation-circle"></i> 
                            Fehler: ${error.message}
                        </div>
                        <button type="button" class="ignis-btn ignis-btn--ghost" onclick="showBulkDeleteModal()">
                            <i class="fa-solid fa-arrow-left"></i> Zurück
                        </button>
                    `;
                });
        };

        window.executeBulkDelete = function() {
            const deleteButton = event.target;
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
            formData.append('timePeriod', window.bulkDeleteTimePeriod || '30');

            fetch('<?= BASE_PATH ?>api/enotf/bulk-delete-empty.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('bulkDeleteContent').innerHTML = `
                        <div class="ignis-alert ignis-alert--success">
                            <i class="fa-solid fa-check-circle"></i> 
                            <strong>Erfolgreich!</strong>
                            <p class="mb-0 mt-2">${data.deleted} Protokoll(e) wurden erfolgreich gelöscht.</p>
                        </div>
                    `;
                        document.getElementById('bulkDeleteFooter').style.display = 'none';

                        // Reload the page after 2 seconds
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        document.getElementById('bulkDeleteContent').innerHTML = `
                        <div class="ignis-alert ignis-alert--danger">
                            <i class="fa-solid fa-exclamation-circle"></i> 
                            Fehler beim Löschen: ${data.message || 'Unbekannter Fehler'}
                        </div>
                    `;
                        deleteButton.innerHTML = originalText;
                        deleteButton.disabled = false;
                    }
                })
                .catch(error => {
                    document.getElementById('bulkDeleteContent').innerHTML = `
                    <div class="ignis-alert ignis-alert--danger">
                        <i class="fa-solid fa-exclamation-circle"></i> 
                        Fehler beim Löschen: ${error.message}
                    </div>
                `;
                    deleteButton.innerHTML = originalText;
                    deleteButton.disabled = false;
                });
        };
    </script>
    <?php include __DIR__ . "/../../../assets/components/footer.php"; ?>
</body>

</html>