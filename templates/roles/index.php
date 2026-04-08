<?php
/**
 * View: Rollenverwaltung
 *
 * @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Role> $roles
 * @var array<string, array<string, string>>                            $permissionGroups
 * @var \PDO                                                            $pdo
 */

use App\Auth\Permissions;
use App\Helpers\Flash;

$badgeColors = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . "/../../assets/components/_base/admin/head.php"; ?>
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
                            <?php if (Permissions::check('full_admin')): ?>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createRoleModal">
                                    <i class="fa-solid fa-plus"></i> Rolle erstellen
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php Flash::render(); ?>
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
                                <?php foreach ($roles as $role):
                                    $editable      = Permissions::check('full_admin');
                                    $permissionsJs = htmlspecialchars(json_encode($role->permissions ?? []), ENT_QUOTES);
                                ?>
                                    <tr>
                                        <td><?= (int) $role->id ?></td>
                                        <td><?= (int) $role->priority ?></td>
                                        <td><span class="badge text-bg-<?= htmlspecialchars($role->color ?? 'secondary') ?>"><?= htmlspecialchars($role->name) ?></span></td>
                                        <td>
                                            <?php if ($editable): ?>
                                                <a title="Rolle bearbeiten" href="#" class="btn btn-sm btn-soft-primary btn-icon edit-btn"
                                                   data-bs-toggle="modal" data-bs-target="#editRoleModal"
                                                   data-id="<?= (int) $role->id ?>"
                                                   data-name="<?= htmlspecialchars($role->name) ?>"
                                                   data-priority="<?= (int) $role->priority ?>"
                                                   data-color="<?= htmlspecialchars($role->color ?? '') ?>"
                                                   data-perms="<?= $permissionsJs ?>">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <?php if (Permissions::check('admin')): ?>
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

                            <div class="mb-3">
                                <label class="form-label">Berechtigungen</label>
                                <?php foreach ($permissionGroups as $groupName => $group): ?>
                                    <div class="mb-3 border-bottom pb-2">
                                        <h6 class="mb-2"><span class="form-hint"><?= htmlspecialchars($groupName) ?></span></h6>
                                        <div class="row">
                                            <?php foreach ($group as $perm => $desc): ?>
                                                <div class="col-6 mb-1">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= htmlspecialchars($perm) ?>" id="perm-<?= htmlspecialchars($perm) ?>">
                                                        <label class="form-check-label" for="perm-<?= htmlspecialchars($perm) ?>"><?= $desc ?></label>
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
                                    <?php foreach ($badgeColors as $color): ?>
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
    <?php endif; ?>

    <!-- CREATE MODAL -->
    <?php if (Permissions::check('full_admin')): ?>
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
                                    <?php foreach ($permissionGroups as $groupName => $group): ?>
                                        <div class="mb-2 border-bottom pb-2">
                                            <h6 class="mb-2"><span class="form-hint"><?= htmlspecialchars($groupName) ?></span></h6>
                                            <div class="row">
                                                <?php foreach ($group as $perm => $desc): ?>
                                                    <div class="col-6 mb-1">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= htmlspecialchars($perm) ?>" id="perm-create-<?= htmlspecialchars($perm) ?>">
                                                            <label class="form-check-label" for="perm-create-<?= htmlspecialchars($perm) ?>"><?= $desc ?></label>
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
                                    <?php foreach ($badgeColors as $color): ?>
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

    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net/js/dataTables.min.js"></script>
    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#table-rollen').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [5, 10, 20],
                pageLength: 10,
                order: [[1, 'asc']],
                columnDefs: [{ orderable: false, targets: -1 }],
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

            const deleteBtn = document.getElementById('delete-role-btn');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function() {
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
            }
        });
    </script>
</body>

</html>
