<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];

    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;

if (!Permissions::check(['admin', 'pois.view'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
}
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    include __DIR__ . '/../../assets/components/_base/admin/head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . "/../../assets/components/navbar.php"; ?>
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
                        <span class="current">POIs</span>
                    </nav>
                    <div class="page-header mb-4">
                        <h1>POI-Verwaltung</h1>
                        <div class="header-actions">
                            <?php if (Permissions::check(['admin', 'pois.manage'])) : ?>
                                <div class="d-flex gap-2">
                                    <a href="<?= BASE_PATH ?>settings/pois/access-codes.php" class="btn btn-soft-warning">
                                        <i class="fa-solid fa-key"></i> Krankenhaus-Zugänge
                                    </a>
                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createPoiModal">
                                        <i class="fa-solid fa-plus"></i> POI erstellen
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                    Flash::render();
                    ?>
                    <div class="mb-3">
                        <div class="btn-toolbar-group" id="statusFilter">
                            <button class="btn active" data-filter="">Alle</button>
                            <button class="btn" data-filter="Ja">Aktiv</button>
                            <button class="btn" data-filter="Nein">Inaktiv</button>
                        </div>
                    </div>
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-pois">
                            <thead>
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Straße</th>
                                    <th scope="col">HNR</th>
                                    <th scope="col">Ort</th>
                                    <th scope="col">Ortsteil</th>
                                    <th scope="col">Typ</th>
                                    <th scope="col">Aktiv?</th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                require __DIR__ . '/../../assets/config/database.php';
                                $stmt = $pdo->prepare("SELECT * FROM intra_edivi_pois ORDER BY name ASC");
                                $stmt->execute();
                                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($result as $row) {
                                    $dimmed = '';

                                    switch ($row['active']) {
                                        case 0:
                                            $poiActive = "<span class='badge-status status-danger'><span class='status-dot'></span>Nein</span>";
                                            $dimmed = "style='color:var(--tag-color)'";
                                            break;
                                        default:
                                            $poiActive = "<span class='badge-status status-success'><span class='status-dot'></span>Ja</span>";
                                            break;
                                    }

                                    $strasse = htmlspecialchars($row['strasse'] ?? '-');
                                    $hnr = htmlspecialchars($row['hnr'] ?? '-');
                                    $ortsteil = htmlspecialchars($row['ortsteil'] ?? '-');
                                    $typ = htmlspecialchars($row['typ'] ?? '-');

                                    $actions = '';
                                    if (Permissions::check(['admin', 'pois.manage'])) {
                                        // Add departments button for hospitals
                                        if ($row['typ'] === 'Krankenhaus' || $row['typ'] === 'Klinik') {
                                            $actions .= "<a title='Fachrichtungen verwalten' href='" . BASE_PATH . "settings/pois/departments.php?poi_id={$row['id']}' class='btn btn-sm btn-outline-secondary btn-icon me-1'><i class='fa-solid fa-hospital'></i></a>";
                                        }
                                        $actions .= "<a title='POI bearbeiten' href='#' class='btn btn-sm btn-soft-primary btn-icon edit-btn' data-bs-toggle='modal' data-bs-target='#editPoiModal' data-id='{$row['id']}' data-name='" . htmlspecialchars($row['name']) . "' data-strasse='" . htmlspecialchars($row['strasse'] ?? '') . "' data-hnr='" . htmlspecialchars($row['hnr'] ?? '') . "' data-ort='" . htmlspecialchars($row['ort']) . "' data-ortsteil='" . htmlspecialchars($row['ortsteil'] ?? '') . "' data-typ='" . htmlspecialchars($row['typ'] ?? '') . "' data-active='{$row['active']}'><i class='fa-solid fa-pen'></i></a>";
                                    }

                                    echo "<tr>";
                                    echo "<td " . $dimmed . ">" . htmlspecialchars($row['name']) . "</td>";
                                    echo "<td " . $dimmed . ">" . $strasse . "</td>";
                                    echo "<td " . $dimmed . ">" . $hnr . "</td>";
                                    echo "<td " . $dimmed . ">" . htmlspecialchars($row['ort']) . "</td>";
                                    echo "<td " . $dimmed . ">" . $ortsteil . "</td>";
                                    echo "<td " . $dimmed . ">" . $typ . "</td>";
                                    echo "<td>" . $poiActive . "</td>";
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

    <!-- EDIT MODAL -->
    <?php if (Permissions::check('admin')) : ?>
        <div class="modal fade" id="editPoiModal" tabindex="-1" aria-labelledby="editPoiModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/pois/update.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editPoiModalLabel">POI bearbeiten <small class="text-muted" id="poi-id-display"></small></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id" id="poi-id">

                            <div class="mb-3">
                                <label for="poi-name" class="form-label">Name / Objekt / Einrichtung *</label>
                                <input type="text" class="form-control" name="name" id="poi-name" required>
                            </div>

                            <div class="mb-3">
                                <label for="poi-strasse" class="form-label">Straße</label>
                                <input type="text" class="form-control" name="strasse" id="poi-strasse">
                            </div>

                            <div class="mb-3">
                                <label for="poi-hnr" class="form-label">Hausnummer / Postal</label>
                                <input type="text" class="form-control" name="hnr" id="poi-hnr">
                            </div>

                            <div class="mb-3">
                                <label for="poi-ort" class="form-label">Ort *</label>
                                <input type="text" class="form-control" name="ort" id="poi-ort" required>
                            </div>

                            <div class="mb-3">
                                <label for="poi-ortsteil" class="form-label">Ortsteil</label>
                                <input type="text" class="form-control" name="ortsteil" id="poi-ortsteil">
                            </div>

                            <div class="mb-3">
                                <label for="poi-typ" class="form-label">Typ</label>
                                <select class="form-select" name="typ" id="poi-typ" data-custom-dropdown="true">
                                    <option value="">--- Kein Typ ---</option>
                                    <option value="Polizeiwache">Polizeiwache</option>
                                    <option value="Rettungswache">Rettungswache</option>
                                    <option value="Feuerwache">Feuerwache</option>
                                    <option value="Krankenhaus">Krankenhaus</option>
                                    <option value="Klinik">Ärztliche Praxis / Klinik</option>
                                    <option value="Behörde">Behörde</option>
                                    <option value="Schule">Schule / Bildungseinrichtung</option>
                                    <option value="Sonstiges">Sonstiges</option>
                                </select>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="active" id="poi-active">
                                <label class="form-check-label" for="poi-active">Aktiv?</label>
                            </div>

                        </div>
                        <div class="modal-footer d-flex justify-content-between">
                            <button type="button" class="btn btn-ghost-danger" id="delete-poi-btn">Löschen</button>

                            <div>
                                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Schließen</button>
                                <button type="submit" class="btn btn-soft-primary">Speichern</button>
                            </div>
                        </div>
                    </form>

                    <form id="delete-poi-form" action="<?= BASE_PATH ?>settings/pois/delete.php" method="POST" style="display:none;">
                        <input type="hidden" name="id" id="poi-delete-id">
                    </form>

                </div>
            </div>
        </div>
    <?php endif; ?>
    <!-- EDIT MODAL END -->

    <!-- CREATE MODAL -->
    <?php if (Permissions::check('admin')) : ?>
        <div class="modal fade" id="createPoiModal" tabindex="-1" aria-labelledby="createPoiModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/pois/create.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createPoiModalLabel">Neuen POI anlegen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">

                            <div class="mb-3">
                                <label for="new-poi-name" class="form-label">Name / Objekt / Einrichtung *</label>
                                <input type="text" class="form-control" name="name" id="new-poi-name" required>
                            </div>

                            <div class="mb-3">
                                <label for="new-poi-strasse" class="form-label">Straße</label>
                                <input type="text" class="form-control" name="strasse" id="new-poi-strasse">
                            </div>

                            <div class="mb-3">
                                <label for="new-poi-hnr" class="form-label">Hausnummer / Postal</label>
                                <input type="text" class="form-control" name="hnr" id="new-poi-hnr">
                            </div>

                            <div class="mb-3">
                                <label for="new-poi-ort" class="form-label">Ort *</label>
                                <input type="text" class="form-control" name="ort" id="new-poi-ort" required>
                            </div>

                            <div class="mb-3">
                                <label for="new-poi-ortsteil" class="form-label">Ortsteil</label>
                                <input type="text" class="form-control" name="ortsteil" id="new-poi-ortsteil">
                            </div>

                            <div class="mb-3">
                                <label for="new-poi-typ" class="form-label">Typ</label>
                                <select class="form-select" name="typ" id="new-poi-typ" data-custom-dropdown="true">
                                    <option value="">--- Kein Typ ---</option>
                                    <option value="Polizeiwache">Polizeiwache</option>
                                    <option value="Rettungswache">Rettungswache</option>
                                    <option value="Feuerwache">Feuerwache</option>
                                    <option value="Krankenhaus">Krankenhaus</option>
                                    <option value="Klinik">Ärztliche Praxis / Klinik</option>
                                    <option value="Behörde">Behörde</option>
                                    <option value="Schule">Schule / Bildungseinrichtung</option>
                                    <option value="Sonstiges">Sonstiges</option>
                                </select>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="active" id="new-poi-active" checked>
                                <label class="form-check-label" for="new-poi-active">Aktiv?</label>
                            </div>

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
    <!-- CREATE MODAL END -->


    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net/js/dataTables.min.js"></script>
    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            var table = $('#table-pois').DataTable({
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
                    "infoFiltered": "| Gefiltert von _MAX_ POIs",
                    "infoPostFix": "",
                    "thousands": ",",
                    "lengthMenu": "_MENU_ POIs pro Seite anzeigen",
                    "loadingRecords": "Lade...",
                    "processing": "Verarbeite...",
                    "search": "POI suchen:",
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

            // Segmented control filtering
            document.querySelectorAll('#statusFilter .btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('#statusFilter .btn').forEach(function(b) { b.classList.remove('active'); });
                    this.classList.add('active');
                    table.column(6).search(this.dataset.filter).draw();
                });
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    document.getElementById('poi-id').value = id;
                    document.getElementById('poi-id-display').textContent = '(ID: ' + id + ')';
                    document.getElementById('poi-name').value = this.dataset.name;
                    document.getElementById('poi-strasse').value = this.dataset.strasse || '';
                    document.getElementById('poi-hnr').value = this.dataset.hnr || '';
                    document.getElementById('poi-ort').value = this.dataset.ort;
                    document.getElementById('poi-ortsteil').value = this.dataset.ortsteil || '';
                    document.getElementById('poi-typ').value = this.dataset.typ || '';
                    document.getElementById('poi-active').checked = this.dataset.active == 1;

                    document.getElementById('poi-delete-id').value = id;
                });
            });

            document.getElementById('delete-poi-btn').addEventListener('click', function() {
                showConfirm('Möchtest du diesen POI wirklich löschen?', {
                    danger: true,
                    confirmText: 'Löschen',
                    title: 'POI löschen'
                }).then(result => {
                    if (result) {
                        document.getElementById('delete-poi-form').submit();
                    }
                });
            });
        });
    </script>
    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>