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

if (!Permissions::check(['admin', 'users.view'])) {
    header("Location: " . BASE_PATH . "index.php");
}
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    include __DIR__ . "/../../assets/components/_base/admin/head.php";
    ?>
</head>

<body data-bs-theme="dark" data-page="benutzer">
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
                        <a href="<?= BASE_PATH ?>benutzer/list.php">Benutzer</a>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <span class="current">Rollen</span>
                    </nav>
                    <div class="page-header mb-4">
                        <h1>Rollenverwaltung</h1>
                        <div class="header-actions">
                            <?php if (Permissions::check('full_admin')) : ?>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createRoleModal">
                                    <i class="fa-solid fa-plus"></i> Rolle erstellen
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                    Flash::render();
                    ?>
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-rollen">
                            <thead>
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">Priorität</th>
                                    <th scope="col">Bezeichnung</th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                require __DIR__ . '/../../assets/config/database.php';
                                $stmt = $pdo->prepare("SELECT * FROM intra_users_roles");
                                $stmt->execute();
                                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($result as $row) {

                                    $actions = (Permissions::check('full_admin'))
                                        ? "<a title='Rolle bearbeiten' href='#' class='btn btn-sm btn-soft-primary btn-icon edit-btn' data-bs-toggle='modal' data-bs-target='#editRoleModal' data-id='{$row['id']}' data-name='{$row['name']}' data-priority='{$row['priority']}' data-color='{$row['color']}' data-perms='{$row['permissions']}'><i class='fa-solid fa-pen'></i></a>"
                                        : "";

                                    echo "<tr>";
                                    echo "<td>" . $row['id'] . "</td>";
                                    echo "<td>" . $row['priority'] . "</td>";
                                    echo "<td><span class='badge text-bg-" . $row['color'] . "'>" . $row['name'] . "</span></td>";
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
        <div class="modal fade" id="editRoleModal" tabindex="-1" aria-labelledby="editRoleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>benutzer/rollen/update.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editRoleModalLabel">Rolle bearbeiten</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id" id="role-id">

                            <div class="mb-3">
                                <label for="role-name" class="form-label">Bezeichnung</label>
                                <input type="text" class="form-control" name="name" id="role-name" required>
                            </div>

                            <div class="mb-3">
                                <label for="role-priority" class="form-label">Priorität <small class="form-hint">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                                <input type="number" class="form-control" name="priority" id="role-priority" required>
                            </div>

                            <?php
                            $permission_groups = [
                                'Anträge' => [
                                    'application.view' => 'Anträge ansehen',
                                    'application.edit' => 'Anträge bearbeiten'
                                ],
                                'Protokolle' => [
                                    'edivi.view' => 'eNOTF Protokolle ansehen',
                                    'edivi.edit' => 'eNOTF Protokolle bearbeiten',
                                    'enotf.view' => 'eNOTF System nutzen',
                                    'manv.manage' => 'MANV-Lagen verwalten',
                                    'fire.incident.qm' => 'FW Einsatzprotokolle bearbeiten'
                                ],
                                'Wissensdatenbank' => [
                                    'kb.view' => 'Wissensdatenbank ansehen',
                                    'kb.edit' => 'Wissensdatenbank bearbeiten',
                                    'kb.archive' => 'Einträge archivieren'
                                ],
                                'Benutzer' => [
                                    'users.view' => 'Benutzer ansehen',
                                    'users.edit' => 'Benutzer bearbeiten',
                                    'users.create' => 'Registrierungscodes erstellen',
                                    'users.delete' => 'Benutzer löschen'
                                ],
                                'Personal' => [
                                    'personnel.view' => 'Mitarbeiter ansehen',
                                    'personnel.edit' => 'Mitarbeiter bearbeiten',
                                    'personnel.delete' => 'Mitarbeiter löschen',
                                    'personnel.comment.delete' => 'Mitarbeiter-Kommentare löschen',
                                    'personnel.documents.manage' => 'Mitarbeiter-Dokumente verwalten',
                                    'audit.view' => 'Logs einsehen',
                                ],
                                'Fahrtenbuch' => [
                                    'fahrtenbuch.view' => 'Fahrtenbuch ansehen',
                                    'fahrtenbuch.manage' => 'Fahrtenbuch verwalten (erstellen, bearbeiten, löschen)'
                                ],
                                'Sonstiges' => [
                                    'admin' => '<strong> Admin (Alle Rechte)</strong>',
                                    'dashboard.manage' => 'Dashboard verwalten',
                                    'vehicles.view' => 'Fahrzeuge ansehen',
                                    'vehicles.manage' => 'Fahrzeuge verwalten',
                                    'pois.view' => 'POIs ansehen',
                                    'pois.manage' => 'POIs und Krankenhaus-Fachrichtungen verwalten'
                                ]
                            ];
                            ?>
                            <div class="mb-3">
                                <label class="form-label">Berechtigungen</label>

                                <?php foreach ($permission_groups as $groupName => $group): ?>
                                    <div class="mb-3 border-bottom pb-2">
                                        <h6 class="mb-2"><span class="form-hint"><?= $groupName ?></span></h6>
                                        <div class="row">
                                            <?php foreach ($group as $perm => $desc): ?>
                                                <div class="col-6 mb-1">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= $perm ?>" id="perm-<?= $perm ?>">
                                                        <label class="form-check-label" for="perm-<?= $perm ?>">
                                                            <?= $desc ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Badge</label>
                                <div class="row">
                                    <?php
                                    $colors = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];
                                    foreach ($colors as $color) :
                                    ?>
                                        <div class="col-6 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="color" id="role-color-<?= $color ?>" value="<?= $color ?>" required>
                                                <label class="form-check-label w-100" for="role-color-<?= $color ?>">
                                                    <span class="badge text-bg-<?= $color ?> w-100 py-2 d-block text-center"><?= ucfirst($color) ?></span>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                        </div>
                        <div class="modal-footer d-flex justify-content-between">
                            <button type="button" class="btn btn-ghost-danger" id="delete-role-btn">Löschen</button>
                            <div>
                                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Schließen</button>
                                <button type="submit" class="btn btn-soft-primary">Speichern</button>
                            </div>
                        </div>
                    </form>

                    <form id="delete-role-form" action="<?= BASE_PATH ?>benutzer/rollen/delete.php" method="POST" style="display:none;">
                        <input type="hidden" name="id" id="role-delete-id">
                    </form>
                </div>
            </div>
        </div>
        </div>
    <?php endif; ?>
    <!-- MODAL END -->
    <!-- MODAL 2 BEGIN -->
    <?php if (Permissions::check('full_admin')) : ?>
        <div class="modal fade" id="createRoleModal" tabindex="-1" aria-labelledby="createRoleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>benutzer/rollen/create.php" method="POST">

                        <div class="modal-header">
                            <h5 class="modal-title" id="createRoleModalLabel">Neue Rolle erstellen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>

                        <div class="modal-body">

                            <div class="mb-3">
                                <label for="new-role-name" class="form-label">Bezeichnung</label>
                                <input type="text" class="form-control" name="name" id="new-role-name" required>
                            </div>

                            <div class="mb-3">
                                <label for="new-role-priority" class="form-label">Priorität</label>
                                <input type="number" class="form-control" name="priority" id="new-role-priority" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Berechtigungen</label>
                                <div class="row">
                                    <?php foreach ($permission_groups as $groupName => $group): ?>
                                        <div class="mb-2 border-bottom pb-2">
                                            <h6 class="mb-2"><span class="form-hint"><?= $groupName ?></span></h6>
                                            <div class="row">
                                                <?php foreach ($group as $perm => $desc): ?>
                                                    <div class="col-6 mb-1">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= $perm ?>" id="perm-create-<?= $perm ?>">
                                                            <label class="form-check-label" for="perm-create-<?= $perm ?>"><?= $desc ?></label>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Badge</label>
                                <div class="row">
                                    <?php
                                    $colors = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];
                                    foreach ($colors as $color) :
                                    ?>
                                        <div class="col-6 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="color" id="new-role-color-<?= $color ?>" value="<?= $color ?>" required>
                                                <label class="form-check-label w-100" for="new-role-color-<?= $color ?>">
                                                    <span class="badge text-bg-<?= $color ?> w-100 py-2 d-block text-center"><?= ucfirst($color) ?></span>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
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
            var table = $('#table-rollen').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [5, 10, 20],
                pageLength: 10,
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
                    "infoFiltered": "| Gefiltert von _MAX_ Rollen",
                    "infoPostFix": "",
                    "thousands": ",",
                    "lengthMenu": "_MENU_ Rollen pro Seite anzeigen",
                    "loadingRecords": "Lade...",
                    "processing": "Verarbeite...",
                    "search": "Rolle suchen:",
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
                    document.getElementById('role-id').value = id;
                    document.getElementById('role-name').value = this.dataset.name;
                    document.getElementById('role-priority').value = this.dataset.priority;

                    const perms = JSON.parse(this.dataset.perms || '[]');

                    document.querySelectorAll('input[name="permissions[]"]').forEach(checkbox => {
                        checkbox.checked = perms.includes(checkbox.value);
                    });

                    const colorValue = this.dataset.color || '';
                    const radio = document.getElementById('role-color-' + colorValue);
                    if (radio) radio.checked = true;

                    document.getElementById('role-delete-id').value = id;
                });
            });

            document.getElementById('delete-role-btn').addEventListener('click', function() {
                showConfirm('Möchtest du diese Rolle wirklich löschen?', {
                    danger: true,
                    confirmText: 'Löschen',
                    title: 'Rolle löschen'
                }).then(result => {
                    if (result) {
                        document.getElementById('delete-role-form').submit();
                    }
                });
            });
        });
    </script>
</body>

</html>