<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];

    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\EnotfUrl;
use App\Helpers\Flash;

if (!Permissions::check(['admin', 'edivi.view'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
}
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
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <div class="d-flex justify-content-between align-items-center mb-5">
                        <h1 class="mb-0">Zielverwaltung</h1>

                        <?php if (Permissions::check('admin')) : ?>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createFahrzeugModal">
                                <i class="fa-solid fa-plus"></i> Ziel erstellen
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php
                    Flash::render();
                    ?>
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-ziele">
                            <thead>
                                <th scope="col">Priorität</th>
                                <th scope="col">Bezeichnung</th>
                                <th scope="col">Transport?</th>
                                <th scope="col">Aktiv?</th>
                                <th scope="col"></th>
                            </thead>
                            <tbody>
                                <?php
                                require __DIR__ . '/../../../assets/config/database.php';
                                $stmt = $pdo->prepare("SELECT * FROM intra_edivi_ziele");
                                $stmt->execute();
                                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($result as $row) {
                                    switch ($row['transport']) {
                                        case 0:
                                            $docYes = "<span class='badge-status status-danger'><span class='status-dot'></span>Nein</span>";
                                            break;
                                        default:
                                            $docYes = "<span class='badge-status status-success'><span class='status-dot'></span>Ja</span>";
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

                                    $actions = (Permissions::check('admin'))
                                        ? "<a title='Ziel bearbeiten' href='#' class='btn btn-sm btn-soft-primary btn-icon edit-btn' data-bs-toggle='modal' data-bs-target='#editFahrzeugModal' data-id='{$row['id']}' data-name='{$row['name']}' data-priority='{$row['priority']}' data-identifier='{$row['identifier']}' data-transport='{$row['transport']}' data-active='{$row['active']}'><i class='fa-solid fa-pen'></i></a>"
                                        : "";

                                    echo "<tr>";
                                    echo "<td " . $dimmed . ">" . $row['priority'] . "</td>";
                                    echo "<td " . $dimmed . ">" . $row['name'] . "</td>";
                                    echo "<td>" . $docYes . "</td>";
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
                    <form action="<?= EnotfUrl::adminZielverwaltung('update') ?>" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editFahrzeugModalLabel">Ziel bearbeiten</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id" id="fahrzeug-id">

                            <div class="mb-3">
                                <label for="fahrzeug-name" class="form-label">Bezeichnung</label>
                                <input type="text" class="form-control" name="name" id="fahrzeug-name" required>
                            </div>

                            <div class="mb-3">
                                <label for="fahrzeug-identifier" class="form-label">Identifier <small class="form-hint">(eindeutige interne Kennung)</small></label>
                                <input type="text" class="form-control" name="identifier" id="fahrzeug-identifier" required>
                            </div>

                            <div class="mb-3">
                                <label for="fahrzeug-priority" class="form-label">Priorität <small class="form-hint">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                                <input type="number" class="form-control" name="priority" id="fahrzeug-priority" required>
                            </div>

                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="transport" id="fahrzeug-transport">
                                <label class="form-check-label" for="fahrzeug-transport">Transport? <small class="form-hint">(Wenn nicht angewählt findet <u>KEIN</u> Transport statt)</small></label>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="active" id="fahrzeug-active">
                                <label class="form-check-label" for="fahrzeug-active">Aktiv?</label>
                            </div>

                        </div>
                        <div class="modal-footer d-flex justify-content-between">
                            <button type="button" class="btn btn-ghost-danger" id="delete-fahrzeug-btn">Löschen</button>

                            <div>
                                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Schließen</button>
                                <button type="submit" class="btn btn-soft-primary">Speichern</button>
                            </div>
                        </div>
                    </form>

                    <form id="delete-fahrzeug-form" action="<?= EnotfUrl::adminZielverwaltung('delete') ?>" method="POST" style="display:none;">
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
                    <form action="<?= EnotfUrl::adminZielverwaltung('create') ?>" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createFahrzeugModalLabel">Neues Ziel anlegen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">

                            <div class="mb-3">
                                <label for="new-fahrzeug-name" class="form-label">Bezeichnung</label>
                                <input type="text" class="form-control" name="name" id="new-fahrzeug-name" required>
                            </div>

                            <div class="mb-3">
                                <label for="new-fahrzeug-identifier" class="form-label">Identifier <small class="form-hint">(eindeutige interne Kennung)</small></label>
                                <input type="text" class="form-control" name="identifier" id="new-fahrzeug-identifier" required>
                            </div>

                            <div class="mb-3">
                                <label for="new-fahrzeug-priority" class="form-label">Priorität <small class="form-hint">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                                <input type="number" class="form-control" name="priority" id="new-fahrzeug-priority" required>
                            </div>

                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="transport" id="new-fahrzeug-transport">
                                <label class="form-check-label" for="new-fahrzeug-transport">Transport? <small class="form-hint">(Wenn nicht angewählt findet <u>KEIN</u> Transport statt)</small></label>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="active" id="new-fahrzeug-active" checked>
                                <label class="form-check-label" for="new-fahrzeug-active">Aktiv?</label>
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
            var table = $('#table-ziele').DataTable({
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
                    "infoFiltered": "| Gefiltert von _MAX_ Zielen",
                    "infoPostFix": "",
                    "thousands": ",",
                    "lengthMenu": "_MENU_ Ziele pro Seite anzeigen",
                    "loadingRecords": "Lade...",
                    "processing": "Verarbeite...",
                    "search": "Ziele suchen:",
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
                    document.getElementById('fahrzeug-priority').value = this.dataset.priority;
                    document.getElementById('fahrzeug-identifier').value = this.dataset.identifier;
                    document.getElementById('fahrzeug-transport').checked = this.dataset.transport == 1;
                    document.getElementById('fahrzeug-active').checked = this.dataset.active == 1;

                    document.getElementById('fahrzeug-delete-id').value = id;
                });
            });

            document.getElementById('delete-fahrzeug-btn').addEventListener('click', function() {
                showConfirm('Möchtest du dieses Ziel wirklich löschen?', {
                    danger: true,
                    confirmText: 'Löschen',
                    title: 'Ziel löschen'
                }).then(result => {
                    if (result) {
                        document.getElementById('delete-fahrzeug-form').submit();
                    }
                });
            });
        });
    </script>
    <?php include __DIR__ . "/../../../assets/components/footer.php"; ?>
</body>

</html>