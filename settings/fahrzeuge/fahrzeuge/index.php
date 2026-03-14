<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];

    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;

if (!Permissions::check(['admin', 'vehicles.view'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <?php
    include __DIR__ . '/../../../assets/components/_base/admin/head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="fahrzeuge">
    <?php include __DIR__ . "/../../../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <nav class="admin-breadcrumb">
                        <a href="<?= BASE_PATH ?>index.php">Dashboard</a>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <span>Einstellungen</span>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <span class="current">Fahrzeuge</span>
                    </nav>
                    <div class="page-header mb-4">
                        <h1>Fahrzeugverwaltung</h1>
                        <div class="header-actions">
                            <a href="<?= BASE_PATH ?>settings/fahrzeuge/defekte/index.php" class="btn btn-outline-warning">
                                <i class="fa-solid fa-triangle-exclamation"></i> Defekt-Meldungen
                            </a>
                            <?php if (Permissions::check(['admin', 'vehicles.manage'])) : ?>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createFahrzeugModal">
                                    <i class="fa-solid fa-plus"></i> Fahrzeug erstellen
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                    Flash::render();
                    ?>
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-fahrzeuge">
                            <thead>
                                <tr>
                                    <th scope="col">Priorität</th>
                                    <th scope="col">Bezeichnung (Typ)</th>
                                    <th scope="col">Kennzeichen</th>
                                    <th scope="col">Fahrzeugtyp</th>
                                    <th scope="col">Defekte</th>
                                    <th scope="col">Aktiv?</th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                require __DIR__ . '/../../../assets/config/database.php';
                                try {
                                    $stmt = $pdo->prepare("SELECT f.*,
                                        (SELECT COUNT(*) FROM intra_fahrzeuge_defects d WHERE d.vehicle_id = f.id AND d.status != 'resolved') AS open_defects,
                                        (SELECT MIN(d.vehicle_operable) FROM intra_fahrzeuge_defects d WHERE d.vehicle_id = f.id AND d.status != 'resolved') AS min_operable
                                        FROM intra_fahrzeuge f");
                                    $stmt->execute();
                                } catch (PDOException $e) {
                                    // Fallback: Tabelle existiert noch nicht
                                    $stmt = $pdo->prepare("SELECT *, 0 AS open_defects, NULL AS min_operable FROM intra_fahrzeuge");
                                    $stmt->execute();
                                }
                                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($result as $row) {
                                    switch ($row['rd_type']) {
                                        case 1:
                                            $docYes = "<span class='badge text-bg-warning'>RD - Mit NA</span>";
                                            break;
                                        case 2:
                                            $docYes = "<span class='badge text-bg-success'>RD - Ohne NA</span>";
                                            break;
                                        case 3:
                                            $docYes = "<span class='badge text-bg-danger'>Feuerwehr</span>";
                                            break;
                                        default:
                                            $docYes = "<span class='badge text-bg-dark'>Andere</span>";
                                            break;
                                    }

                                    $dimmed = '';

                                    switch ($row['active']) {
                                        case 0:
                                            $vehActive = "<span class='badge-status status-danger'><span class='status-dot'></span>Nein</span>";
                                            $dimmed = "style='color:var(--tag-color)'";
                                            break;
                                        default:
                                            $vehActive = "<span class='badge-status status-success'><span class='status-dot'></span>Ja</span>";
                                            break;
                                    }

                                    $kennzeichen = $row['kennzeichen'] ?? '';
                                    $kennzeichenDisplay = $kennzeichen ?: '-';

                                    $actions = "";
                                    if (Permissions::check(['admin', 'vehicles.manage'])) {
                                        $dataAttrs = [
                                            'id' => $row['id'],
                                            'name' => $row['name'],
                                            'kennzeichen' => $row['kennzeichen'],
                                            'type' => $row['veh_type'],
                                            'priority' => $row['priority'],
                                            'identifier' => $row['identifier'],
                                            'rd_type' => $row['rd_type'],
                                            'active' => $row['active'],
                                            'allowed_jobs' => $row['allowed_jobs'] ?? '',
                                            'tz-grundzeichen' => $row['grundzeichen'] ?? '',
                                            'tz-organisation' => $row['organisation'] ?? '',
                                            'tz-fachaufgabe' => $row['fachaufgabe'] ?? '',
                                            'tz-einheit' => $row['einheit'] ?? '',
                                            'tz-symbol' => $row['symbol'] ?? '',
                                            'tz-typ' => $row['typ'] ?? '',
                                            'tz-text' => $row['text'] ?? '',
                                            'tz-name' => $row['tz_name'] ?? ''
                                        ];

                                        $dataStr = '';
                                        foreach ($dataAttrs as $key => $val) {
                                            $dataStr .= " data-{$key}='" . htmlspecialchars($val, ENT_QUOTES) . "'";
                                        }

                                        $actions .= "<a title='Fahrzeug bearbeiten' href='#' class='btn btn-sm btn-soft-primary btn-icon edit-btn' data-bs-toggle='modal' data-bs-target='#editFahrzeugModal'{$dataStr}><i class='fa-solid fa-pen'></i></a> ";
                                        $actions .= "<a title='Fahrzeug kopieren' href='#' class='btn btn-sm btn-soft-success btn-icon copy-btn'{$dataStr}><i class='fa-solid fa-copy'></i></a>";
                                    }

                                    $openDefects = (int)($row['open_defects'] ?? 0);
                                    $minOperable = $row['min_operable'];
                                    $defectBadge = '';
                                    if ($openDefects > 0) {
                                        $badgeColor = ($minOperable !== null && (int)$minOperable === 0) ? 'danger' : 'warning';
                                        $defectBadge = "<a href='" . BASE_PATH . "settings/fahrzeuge/defekte/index.php?vehicle=" . $row['id'] . "' class='badge text-bg-{$badgeColor}' title='Offene Defekte anzeigen'>{$openDefects}</a>";
                                    } else {
                                        $defectBadge = "<span class='text-muted'>—</span>";
                                    }

                                    echo "<tr>";
                                    echo "<td " . $dimmed . ">" . $row['priority'] . "</td>";
                                    echo "<td " . $dimmed . ">" . $row['name'] . " (" . $row['veh_type'] .  ")</td>";
                                    echo "<td " . $dimmed . ">" . $kennzeichenDisplay . "</td>";
                                    echo "<td>" . $docYes . "</td>";
                                    echo "<td>" . $defectBadge . "</td>";
                                    echo "<td>" . $vehActive . "</td>";
                                    echo "<td>{$actions}</td>";
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

    <!-- MODAL BEGIN -->
    <?php if (Permissions::check('admin')) : ?>
        <div class="modal fade" id="editFahrzeugModal" tabindex="-1" aria-labelledby="editFahrzeugModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/fahrzeuge/fahrzeuge/update.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editFahrzeugModalLabel">Fahrzeug bearbeiten</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id" id="fahrzeug-id">

                            <div class="mb-3">
                                <label for="fahrzeug-name" class="form-label">Bezeichnung <small class="form-hint">(z.B. Funkrufname)</small></label>
                                <input type="text" class="form-control" name="name" id="fahrzeug-name" required>
                            </div>

                            <div class="mb-3">
                                <label for="fahrzeug-kennzeichen" class="form-label">Kennzeichen</label>
                                <input type="text" class="form-control" name="kennzeichen" id="fahrzeug-kennzeichen" required>
                            </div>

                            <div class="mb-3">
                                <label for="fahrzeug-identifier" class="form-label">Identifier <small class="form-hint">(eindeutige interne Kennung)</small></label>
                                <input type="text" class="form-control" name="identifier" id="fahrzeug-identifier" required>
                            </div>

                            <div class="mb-3">
                                <label for="fahrzeug-veh_typ" class="form-label">Typ <small class="form-hint">(RTW,NEF,RTH etc.)</small></label>
                                <input type="text" class="form-control" name="veh_type" id="fahrzeug-veh_typ" required>
                            </div>

                            <div class="mb-3">
                                <label for="fahrzeug-priority" class="form-label">Priorität <small class="form-hint">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                                <input type="number" class="form-control" name="priority" id="fahrzeug-priority" required>
                            </div>

                            <div class="form-group mb-3">
                                <label for="fahrzeug-rd_type">Typ (Rettungsdienstlich)</label>
                                <select class="form-control" name="rd_type" id="fahrzeug-rd_type">
                                    <option value="0">Andere</option>
                                    <option value="1">Rettungsdienst mit NA</option>
                                    <option value="2">Rettungsdienst ohne NA</option>
                                    <option value="3">Feuerwehr</option>
                                </select>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="active" id="fahrzeug-active">
                                <label class="form-check-label" for="fahrzeug-active">Aktiv?</label>
                            </div>

                            <div class="mb-3">
                                <label for="fahrzeug-allowed_jobs" class="form-label">Erlaubte Jobs <small class="form-hint">(kommagetrennt, leer = alle)</small></label>
                                <input type="text" class="form-control" name="allowed_jobs" id="fahrzeug-allowed_jobs" placeholder="z.B. BF,FF_Stadt">
                            </div>

                            <?php
                            $prefix = 'fahrzeug-';
                            $showPreview = true;
                            include __DIR__ . '/../../../assets/components/tactical-symbol-form.php';
                            ?>

                        </div>
                        <div class="modal-footer d-flex justify-content-between">
                            <button type="button" class="btn btn-ghost-danger" id="delete-fahrzeug-btn">Löschen</button>

                            <div>
                                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Schließen</button>
                                <button type="submit" class="btn btn-soft-primary">Speichern</button>
                            </div>
                        </div>
                    </form>

                    <form id="delete-fahrzeug-form" action="<?= BASE_PATH ?>settings/fahrzeuge/fahrzeuge/delete.php" method="POST" style="display:none;">
                        <input type="hidden" name="id" id="fahrzeug-delete-id">
                    </form>

                </div>
            </div>
        </div>
    <?php endif; ?>
    <!-- MODAL END -->
    <!-- MODAL 2 BEGIN -->
    <?php if (Permissions::check('admin')) : ?>
        <div class="modal fade" id="createFahrzeugModal" tabindex="-1" aria-labelledby="createFahrzeugModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/fahrzeuge/fahrzeuge/create.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createFahrzeugModalLabel">Neues Fahrzeug anlegen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">

                            <div class="mb-3">
                                <label for="new-fahrzeug-name" class="form-label">Bezeichnung <small class="form-hint">(z.B. Funkrufname)</small></label>
                                <input type="text" class="form-control" name="name" id="new-fahrzeug-name" required>
                            </div>

                            <div class="mb-3">
                                <label for="new-fahrzeug-kennzeichen" class="form-label">Kennzeichen</label>
                                <input type="text" class="form-control" name="kennzeichen" id="new-fahrzeug-kennzeichen" required>
                            </div>

                            <div class="mb-3">
                                <label for="new-fahrzeug-identifier" class="form-label">Identifier <small class="form-hint">(eindeutige interne Kennung)</small></label>
                                <input type="text" class="form-control" name="identifier" id="new-fahrzeug-identifier" required>
                            </div>

                            <div class="mb-3">
                                <label for="new-fahrzeug-veh_typ" class="form-label">Typ <small class="form-hint">(RTW,NEF,RTH etc.)</small></label>
                                <input type="text" class="form-control" name="veh_type" id="new-fahrzeug-veh_typ" required>
                            </div>

                            <div class="mb-3">
                                <label for="new-fahrzeug-priority" class="form-label">Priorität <small class="form-hint">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                                <input type="number" class="form-control" name="priority" id="new-fahrzeug-priority" required>
                            </div>

                            <div class="form-group mb-3">
                                <label for="new-fahrzeug-rd_type">Typ (Rettungsdienstlich)</label>
                                <select class="form-control" name="rd_type" id="new-fahrzeug-rd_type">
                                    <option value="0">Andere</option>
                                    <option value="1">Rettungsdienst mit NA</option>
                                    <option value="2">Rettungsdienst ohne NA</option>
                                    <option value="3">Feuerwehr</option>
                                </select>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="active" id="new-fahrzeug-active" checked>
                                <label class="form-check-label" for="new-fahrzeug-active">Aktiv?</label>
                            </div>

                            <div class="mb-3">
                                <label for="new-fahrzeug-allowed_jobs" class="form-label">Erlaubte Jobs <small class="form-hint">(kommagetrennt, leer = alle)</small></label>
                                <input type="text" class="form-control" name="allowed_jobs" id="new-fahrzeug-allowed_jobs" placeholder="z.B. BF,FF_Stadt">
                            </div>

                            <?php
                            $prefix = 'new-fahrzeug-';
                            $showPreview = true;
                            include __DIR__ . '/../../../assets/components/tactical-symbol-form.php';
                            ?>

                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Schließen</button>
                            <button type="submit" class="btn btn-success">Erstellen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <!-- MODAL 2 END -->


    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net/js/dataTables.min.js"></script>
    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            var table = $('#table-fahrzeuge').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [10, 20, 50],
                pageLength: 20,
                order: [
                    [0, 'asc']
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
                    "infoFiltered": "| Gefiltert von _MAX_ Fahrzeugen",
                    "infoPostFix": "",
                    "thousands": ",",
                    "lengthMenu": "_MENU_ Fahrzeuge pro Seite anzeigen",
                    "loadingRecords": "Lade...",
                    "processing": "Verarbeite...",
                    "search": "Fahrzeug suchen:",
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
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    document.getElementById('fahrzeug-id').value = id;
                    document.getElementById('fahrzeug-name').value = this.dataset.name;
                    document.getElementById('fahrzeug-kennzeichen').value = this.dataset.kennzeichen || '';
                    document.getElementById('fahrzeug-veh_typ').value = this.dataset.type;
                    document.getElementById('fahrzeug-priority').value = this.dataset.priority;
                    document.getElementById('fahrzeug-identifier').value = this.dataset.identifier;
                    document.getElementById('fahrzeug-rd_type').value = this.dataset.rd_type || '0';
                    document.getElementById('fahrzeug-active').checked = this.dataset.active == 1;
                    document.getElementById('fahrzeug-allowed_jobs').value = this.dataset.allowed_jobs || '';

                    // Reset preview first
                    const previewContainer = document.getElementById('fahrzeug-tz-preview');
                    if (previewContainer) {
                        previewContainer.innerHTML = '<span style="font-size: 48px; color: #999;">Kein Symbol</span>';
                    }

                    // Load tactical symbol data
                    document.getElementById('fahrzeug-grundzeichen').value = this.dataset.tzGrundzeichen || '';
                    document.getElementById('fahrzeug-organisation').value = this.dataset.tzOrganisation || '';
                    document.getElementById('fahrzeug-fachaufgabe').value = this.dataset.tzFachaufgabe || '';
                    document.getElementById('fahrzeug-einheit').value = this.dataset.tzEinheit || '';
                    document.getElementById('fahrzeug-symbol').value = this.dataset.tzSymbol || '';
                    document.getElementById('fahrzeug-typ').value = this.dataset.tzTyp || '';
                    document.getElementById('fahrzeug-text').value = this.dataset.tzText || '';
                    document.getElementById('fahrzeug-tz_name').value = this.dataset.tzName || '';

                    // Auto-trigger preview if grundzeichen exists
                    setTimeout(() => {
                        if (this.dataset.tzGrundzeichen) {
                            document.getElementById('fahrzeug-preview-btn')?.click();
                        }
                    }, 100);

                    document.getElementById('fahrzeug-delete-id').value = id;
                });
            });

            document.getElementById('delete-fahrzeug-btn').addEventListener('click', function() {
                showConfirm('Möchtest du dieses Fahrzeug wirklich löschen?', {
                    danger: true,
                    confirmText: 'Löschen',
                    title: 'Fahrzeug löschen'
                }).then(result => {
                    if (result) {
                        document.getElementById('delete-fahrzeug-form').submit();
                    }
                });
            });

            // Copy button functionality
            document.querySelectorAll('.copy-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();

                    // Fill the create modal with the data from the vehicle to copy
                    document.getElementById('new-fahrzeug-name').value = this.dataset.name;
                    document.getElementById('new-fahrzeug-kennzeichen').value = this.dataset.kennzeichen || '';
                    document.getElementById('new-fahrzeug-veh_typ').value = this.dataset.type;
                    document.getElementById('new-fahrzeug-priority').value = this.dataset.priority;
                    document.getElementById('new-fahrzeug-identifier').value = this.dataset.identifier + '(1)';
                    document.getElementById('new-fahrzeug-rd_type').value = this.dataset.rd_type || '0';
                    document.getElementById('new-fahrzeug-active').checked = this.dataset.active == 1;
                    document.getElementById('new-fahrzeug-allowed_jobs').value = this.dataset.allowed_jobs || '';

                    // Copy tactical symbol data
                    document.getElementById('new-fahrzeug-grundzeichen').value = this.dataset.tzGrundzeichen || '';
                    document.getElementById('new-fahrzeug-organisation').value = this.dataset.tzOrganisation || '';
                    document.getElementById('new-fahrzeug-fachaufgabe').value = this.dataset.tzFachaufgabe || '';
                    document.getElementById('new-fahrzeug-einheit').value = this.dataset.tzEinheit || '';
                    document.getElementById('new-fahrzeug-symbol').value = this.dataset.tzSymbol || '';
                    document.getElementById('new-fahrzeug-typ').value = this.dataset.tzTyp || '';
                    document.getElementById('new-fahrzeug-text').value = this.dataset.tzText || '';
                    document.getElementById('new-fahrzeug-tz_name').value = this.dataset.tzName || '';

                    // Open the create modal
                    const createModal = new bootstrap.Modal(document.getElementById('createFahrzeugModal'));
                    createModal.show();
                });
            });
        });
    </script>
    <?php include __DIR__ . "/../../../assets/components/footer.php"; ?>
</body>

</html>
