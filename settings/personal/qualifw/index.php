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

if (!Permissions::check(['admin', 'personnel.view'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
}
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    include __DIR__ . '/../../../assets/components/_base/admin/head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . "/../../../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <div class="d-flex justify-content-between align-items-center mb-5">
                        <h1 class="mb-0">FW Qualifikationen verwalten</h1>

                        <?php if (Permissions::check('admin')) : ?>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createDienstgradModal">
                                <i class="fa-solid fa-plus"></i> Qualifikation erstellen
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php
                    Flash::render();
                    ?>
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-dienstgrade">
                            <thead>
                                <tr>
                                    <th scope="col">Priorität</th>
                                    <th scope="col">Bezeichnung <i class="fa-solid fa-mars-and-venus"></i></th>
                                    <th scope="col">Bezeichnung <i class="fa-solid fa-mars"></i></th>
                                    <th scope="col">Bezeichnung <i class="fa-solid fa-venus"></i></th>
                                    <th scope="col">Leer?</th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                require __DIR__ . '/../../../assets/config/database.php';
                                $stmt = $pdo->prepare("SELECT * FROM intra_mitarbeiter_fwquali");
                                $stmt->execute();
                                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($result as $row) {
                                    $dimmed = '';

                                    switch ($row['none']) {
                                        case 0:
                                            $dgActive = "<span class='badge-status status-success'><span class='status-dot'></span>Nein</span>";
                                            break;
                                        default:
                                            $dgActive = "<span class='badge-status status-danger'><span class='status-dot'></span>Ja</span>";
                                            $dimmed = "style='color:var(--tag-color)'";
                                            break;
                                    }

                                    $actions = (Permissions::check('admin'))
                                        ? "<a title='Qualifikation bearbeiten' href='#' class='btn btn-sm btn-soft-primary btn-icon edit-btn' data-bs-toggle='modal' data-bs-target='#editDienstgradModal' data-id='{$row['id']}' data-shortname='{$row['shortname']}' data-name='{$row['name']}' data-name_m='{$row['name_m']}' data-name_w='{$row['name_w']}' data-priority='{$row['priority']}' data-none='{$row['none']}'><i class='fa-solid fa-pen'></i></a>"
                                        : "";

                                    echo "<tr>";
                                    echo "<td " . $dimmed . ">" . $row['priority'] . "</td>";
                                    echo "<td " . $dimmed . ">" . $row['name'] . "</td>";
                                    echo "<td " . $dimmed . ">" . $row['name_m'] . "</td>";
                                    echo "<td " . $dimmed . ">" . $row['name_w'] . "</td>";
                                    echo "<td>" . $dgActive . "</td>";
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
        <div class="modal fade" id="editDienstgradModal" tabindex="-1" aria-labelledby="editDienstgradModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/personal/qualifw/update.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editDienstgradModalLabel">FW Qualifikation bearbeiten</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id" id="dienstgrad-id">

                            <div class="mb-3">
                                <label for="dienstgrad-shortname" class="form-label">Kurzbezeichnung <small class="form-hint">(z.B. B1,B2 etc.)</small></label>
                                <input type="text" class="form-control" name="shortname" id="dienstgrad-shortname" required>
                            </div>

                            <div class="mb-3">
                                <label for="dienstgrad-name" class="form-label">Bezeichnung <small class="form-hint">(Allgemein)</small></label>
                                <input type="text" class="form-control" name="name" id="dienstgrad-name" required>
                            </div>

                            <div class="mb-3">
                                <label for="dienstgrad-name_m" class="form-label">Bezeichnung <small class="form-hint">(Männlich)</small></label>
                                <input type="text" class="form-control" name="name_m" id="dienstgrad-name_m" required>
                            </div>

                            <div class="mb-3">
                                <label for="dienstgrad-name_w" class="form-label">Bezeichnung <small class="form-hint">(Weiblich)</small></label>
                                <input type="text" class="form-control" name="name_w" id="dienstgrad-name_w" required>
                            </div>

                            <div class="mb-3">
                                <label for="dienstgrad-priority" class="form-label">Priorität <small class="form-hint">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                                <input type="number" class="form-control" name="priority" id="dienstgrad-priority" required>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="none" id="dienstgrad-none">
                                <label class="form-check-label" for="dienstgrad-none">Leer?</label>
                            </div>

                        </div>
                        <div class="modal-footer d-flex justify-content-between">
                            <button type="button" class="btn btn-ghost-danger" id="delete-dienstgrad-btn">Löschen</button>

                            <div>
                                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Schließen</button>
                                <button type="submit" class="btn btn-soft-primary">Speichern</button>
                            </div>
                        </div>
                    </form>

                    <form id="delete-dienstgrad-form" action="<?= BASE_PATH ?>settings/personal/qualifw/delete.php" method="POST" style="display:none;">
                        <input type="hidden" name="id" id="dienstgrad-delete-id">
                    </form>

                </div>
            </div>
        </div>
    <?php endif; ?>
    <!-- MODAL END -->
    <!-- MODAL 2 BEGIN -->
    <?php if (Permissions::check('admin')) : ?>
        <div class="modal fade" id="createDienstgradModal" tabindex="-1" aria-labelledby="createDienstgradModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/personal/qualifw/create.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createDienstgradModalLabel">FW Qualifikation anlegen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">

                            <div class="mb-3">
                                <label for="new-dienstgrad-shortname" class="form-label">Kurzbezeichnung <small class="form-hint">(z.B. B1,B2 etc.)</small></label>
                                <input type="text" class="form-control" name="shortname" id="new-dienstgrad-shortname" required>
                            </div>

                            <div class="mb-3">
                                <label for="new-dienstgrad-name" class="form-label">Bezeichnung <small class="form-hint">(Allgemein)</small></label>
                                <input type="text" class="form-control" name="name" id="new-dienstgrad-name" required>
                            </div>

                            <div class="mb-3">
                                <label for="new-dienstgrad-name_m" class="form-label">Bezeichnung <small class="form-hint">(Männlich)</small></label>
                                <input type="text" class="form-control" name="name_m" id="new-dienstgrad-name_m" required>
                            </div>

                            <div class="mb-3">
                                <label for="new-dienstgrad-name_w" class="form-label">Bezeichnung <small class="form-hint">(Weiblich)</small></label>
                                <input type="text" class="form-control" name="name_w" id="new-dienstgrad-name_w" required>
                            </div>

                            <div class="mb-3">
                                <label for="new-dienstgrad-priority" class="form-label">Priorität <small class="form-hint">(je niedriger, desto höher)</small></label>
                                <input type="number" class="form-control" name="priority" id="new-dienstgrad-priority" value="0" required>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="none" id="new-dienstgrad-none">
                                <label class="form-check-label" for="new-dienstgrad-none">Leer?</label>
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

    <!-- MODAL 2 END -->


    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net/js/dataTables.min.js"></script>
    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            var table = $('#table-dienstgrade').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [5, 10, 20],
                pageLength: 10,
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
                    "infoFiltered": "| Gefiltert von _MAX_ Qualifikationen",
                    "infoPostFix": "",
                    "thousands": ",",
                    "lengthMenu": "_MENU_ Qualifikationen pro Seite anzeigen",
                    "loadingRecords": "Lade...",
                    "processing": "Verarbeite...",
                    "search": "Qualifikation suchen:",
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
                    document.getElementById('dienstgrad-id').value = id;
                    document.getElementById('dienstgrad-name').value = this.dataset.name;
                    document.getElementById('dienstgrad-name_m').value = this.dataset.name_m;
                    document.getElementById('dienstgrad-name_w').value = this.dataset.name_w;
                    document.getElementById('dienstgrad-priority').value = this.dataset.priority;
                    document.getElementById('dienstgrad-shortname').value = this.dataset.shortname;
                    document.getElementById('dienstgrad-none').checked = this.dataset.none == 1;

                    document.getElementById('dienstgrad-delete-id').value = id;
                });
            });

            document.getElementById('delete-dienstgrad-btn').addEventListener('click', function() {
                showConfirm('Möchtest du diese Qualifikation wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Qualifikation löschen'}).then(result => {
                    if (result) {
                        document.getElementById('delete-dienstgrad-form').submit();
                    }
                });
            });
        });
    </script>

    <?php include __DIR__ . "/../../../assets/components/footer.php"; ?>
</body>

</html>