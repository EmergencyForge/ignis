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

if (!Permissions::check(['admin', 'edivi.view'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <?php
    include __DIR__ . "/../../assets/components/_base/admin/head.php";
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
                    <hr class="text-light my-3">
                    <div class="d-flex justify-content-between align-items-center mb-5">
                        <h1 class="mb-0">Medikamentenverwaltung</h1>

                        <?php if (Permissions::check('admin')) : ?>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createMedikamentModal">
                                <i class="fa-solid fa-plus"></i> Medikament erstellen
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php
                    Flash::render();
                    ?>
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-medikamente">
                            <thead>
                                <th scope="col">Priorität</th>
                                <th scope="col">Wirkstoff</th>
                                <th scope="col">Herstellername</th>
                                <th scope="col">Dosierungen</th>
                                <th scope="col">Aktiv?</th>
                                <th scope="col"></th>
                            </thead>
                            <tbody>
                                <?php
                                require __DIR__ . '/../../assets/config/database.php';
                                $stmt = $pdo->prepare("SELECT * FROM intra_edivi_medikamente ORDER BY wirkstoff ASC");
                                $stmt->execute();
                                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($result as $row) {
                                    $dimmed = '';

                                    switch ($row['active']) {
                                        case 0:
                                            $medActive = "<span class='badge text-bg-danger'>Nein</span>";
                                            $dimmed = "style='color:var(--tag-color)'";
                                            break;
                                        default:
                                            $medActive = "<span class='badge text-bg-success'>Ja</span>";
                                            break;
                                    }

                                    $herstellername = htmlspecialchars($row['herstellername'] ?? '');
                                    $dosierungen = htmlspecialchars($row['dosierungen'] ?? '');
                                    $wirkstoff = htmlspecialchars($row['wirkstoff']);

                                    $actions = (Permissions::check('admin'))
                                        ? "<a title='Medikament bearbeiten' href='#' class='btn btn-sm btn-primary edit-btn' data-bs-toggle='modal' data-bs-target='#editMedikamentModal' data-id='{$row['id']}' data-wirkstoff='{$wirkstoff}' data-herstellername='{$herstellername}' data-dosierungen='{$dosierungen}' data-priority='{$row['priority']}' data-active='{$row['active']}'><i class='fa-solid fa-pen'></i></a>"
                                        : "";

                                    echo "<tr>";
                                    echo "<td " . $dimmed . ">" . $row['priority'] . "</td>";
                                    echo "<td " . $dimmed . ">" . $wirkstoff . "</td>";
                                    echo "<td " . $dimmed . ">" . ($herstellername ?: '<span class="text-muted">-</span>') . "</td>";
                                    echo "<td " . $dimmed . ">" . ($dosierungen ?: '<span class="text-muted">-</span>') . "</td>";
                                    echo "<td>" . $medActive . "</td>";
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
        <div class="modal fade" id="editMedikamentModal" tabindex="-1" aria-labelledby="editMedikamentModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/medikamente/update.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editMedikamentModalLabel">Medikament bearbeiten</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id" id="medikament-id">

                            <div class="mb-3">
                                <label for="medikament-wirkstoff" class="form-label">Wirkstoff</label>
                                <input type="text" class="form-control" name="wirkstoff" id="medikament-wirkstoff" required>
                            </div>

                            <div class="mb-3">
                                <label for="medikament-herstellername" class="form-label">Herstellername <small style="opacity:.5">(optional, z.B. "ASS" für Acetylsalicylsäure)</small></label>
                                <input type="text" class="form-control" name="herstellername" id="medikament-herstellername">
                            </div>

                            <div class="mb-3">
                                <label for="medikament-dosierungen" class="form-label">Vordefinierte Dosierungen <small style="opacity:.5">(kommagetrennt, z.B. "100 mg,250 mg,500 mg")</small></label>
                                <input type="text" class="form-control" name="dosierungen" id="medikament-dosierungen" placeholder="100 mg,250 mg,500 mg">
                            </div>

                            <div class="mb-3">
                                <label for="medikament-priority" class="form-label">Priorität <small style="opacity:.5">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                                <input type="number" class="form-control" name="priority" id="medikament-priority" required>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="active" id="medikament-active">
                                <label class="form-check-label" for="medikament-active">Aktiv?</label>
                            </div>

                        </div>
                        <div class="modal-footer d-flex justify-content-between">
                            <button type="button" class="btn btn-danger" id="delete-medikament-btn">Löschen</button>

                            <div>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                                <button type="submit" class="btn btn-primary">Speichern</button>
                            </div>
                        </div>
                    </form>

                    <form id="delete-medikament-form" action="<?= BASE_PATH ?>settings/medikamente/delete.php" method="POST" style="display:none;">
                        <input type="hidden" name="id" id="medikament-delete-id">
                    </form>

                </div>
            </div>
        </div>
    <?php endif; ?>
    <!-- MODAL END -->
    <!-- MODAL 2 BEGIN -->
    <?php if (Permissions::check('admin')) : ?>
        <div class="modal fade" id="createMedikamentModal" tabindex="-1" aria-labelledby="createMedikamentModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/medikamente/create.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createMedikamentModalLabel">Neues Medikament anlegen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">

                            <div class="mb-3">
                                <label for="new-medikament-wirkstoff" class="form-label">Wirkstoff</label>
                                <input type="text" class="form-control" name="wirkstoff" id="new-medikament-wirkstoff" required>
                            </div>

                            <div class="mb-3">
                                <label for="new-medikament-herstellername" class="form-label">Herstellername <small style="opacity:.5">(optional, z.B. "ASS" für Acetylsalicylsäure)</small></label>
                                <input type="text" class="form-control" name="herstellername" id="new-medikament-herstellername">
                            </div>

                            <div class="mb-3">
                                <label for="new-medikament-dosierungen" class="form-label">Vordefinierte Dosierungen <small style="opacity:.5">(kommagetrennt, z.B. "100 mg,250 mg,500 mg")</small></label>
                                <input type="text" class="form-control" name="dosierungen" id="new-medikament-dosierungen" placeholder="100 mg,250 mg,500 mg">
                            </div>

                            <div class="mb-3">
                                <label for="new-medikament-priority" class="form-label">Priorität <small style="opacity:.5">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                                <input type="number" class="form-control" name="priority" id="new-medikament-priority" required>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="active" id="new-medikament-active" checked>
                                <label class="form-check-label" for="new-medikament-active">Aktiv?</label>
                            </div>

                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
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
            var table = $('#table-medikamente').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [10, 25, 50],
                pageLength: 25,
                order: [
                    [1, 'asc']
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
                    "infoFiltered": "| Gefiltert von _MAX_ Medikamenten",
                    "infoPostFix": "",
                    "thousands": ",",
                    "lengthMenu": "_MENU_ Medikamente pro Seite anzeigen",
                    "loadingRecords": "Lade...",
                    "processing": "Verarbeite...",
                    "search": "Medikamente suchen:",
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
                    document.getElementById('medikament-id').value = id;
                    document.getElementById('medikament-wirkstoff').value = this.dataset.wirkstoff;
                    document.getElementById('medikament-herstellername').value = this.dataset.herstellername;
                    document.getElementById('medikament-dosierungen').value = this.dataset.dosierungen;
                    document.getElementById('medikament-priority').value = this.dataset.priority;
                    document.getElementById('medikament-active').checked = this.dataset.active == 1;

                    document.getElementById('medikament-delete-id').value = id;
                });
            });

            document.getElementById('delete-medikament-btn').addEventListener('click', function() {
                showConfirm('Möchtest du dieses Medikament wirklich löschen?', {
                    danger: true,
                    confirmText: 'Löschen',
                    title: 'Medikament löschen'
                }).then(result => {
                    if (result) {
                        document.getElementById('delete-medikament-form').submit();
                    }
                });
            });
        });
    </script>
    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>